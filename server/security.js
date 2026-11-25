// Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>
// SPDX-License-Identifier: MIT

// Site: https://www.profmugomes.com.br

'use strict';

// ============================================================
// SEGURANCA — CHAVE PRE-COMPARTILHADA (PSK) DINAMICA
//
// A cada execucao do MiPhant e gerada uma chave mestra aleatoria
// de 256 bits, somente em memoria. Dela derivamos (HKDF-SHA256)
// duas subchaves com papeis distintos:
//
//   httpKey -> injetada pelo Chromium nas requisicoes das janelas
//              oficiais (renderer -> servidor HTTPS)
//   phpKey  -> usada para assinar cada requisicao Node -> PHP
//
// A chave NUNCA vai para disco, para process.env, para o banco de
// cookies, para o contextBridge ou para o JS da pagina.
//
// Este modulo nao depende de Electron (testavel de forma isolada).
// A protecao e obrigatoria: nao existe flag de configuracao para
// desliga-la.
// ============================================================

const crypto = require('crypto');

const {
    AUTH_HEADER,
    AUTH_KEY_BYTES,
    AUTH_CLOCK_SKEW,
    AUTH_MAX_FAILURES,
    AUTH_FAILURE_WINDOW
} = require('./config');

// ============================================================
// ESTADO (somente memoria)
// ============================================================

let masterKey = null;
let httpKey = null;
let phpKey = null;
let initialized = false;

let failureCount = 0;
let failureWindowStart = 0;

// ============================================================
// DERIVACAO DE SUBCHAVES
// ============================================================

function deriveKey(master, info) {
    return Buffer.from(
        crypto.hkdfSync('sha256', master, Buffer.alloc(0), info, AUTH_KEY_BYTES)
    );
}

// ============================================================
// CICLO DE VIDA
// ============================================================

function create() {
    if (initialized) return;

    masterKey = crypto.randomBytes(AUTH_KEY_BYTES);
    httpKey = deriveKey(masterKey, 'miphant/http/v1');
    phpKey = deriveKey(masterKey, 'miphant/php/v1');

    failureCount = 0;
    failureWindowStart = Date.now();
    initialized = true;
}

function isActive() {
    return initialized;
}

// Apaga o material criptografico. Buffers permitem fill(0);
// strings nao seriam apagaveis, por isso nao as mantemos.
function wipe() {
    for (const key of [masterKey, httpKey, phpKey]) {
        if (Buffer.isBuffer(key)) {
            key.fill(0);
        }
    }

    masterKey = null;
    httpKey = null;
    phpKey = null;
    initialized = false;
    failureCount = 0;
    failureWindowStart = 0;
}

// ============================================================
// FINGERPRINT PARA LOG (nunca expoe a chave)
// ============================================================

function getFingerprint() {
    if (!initialized) return null;

    return crypto
        .createHash('sha256')
        .update(masterKey)
        .digest('hex')
        .slice(0, 8);
}

// ============================================================
// HEADER DO RENDERER
// ============================================================

function getHttpAuthHeader() {
    if (!initialized) return null;

    return {
        name: AUTH_HEADER,
        value: httpKey.toString('hex')
    };
}

// Usada apenas pelo processo PHP (ambiente de spawn). Nunca sai do main.
function getPhpKeyHex() {
    return initialized ? phpKey.toString('hex') : '';
}

// ============================================================
// VALIDACAO DA REQUISICAO HTTP
//
// Fail closed: qualquer duvida => negado.
// ============================================================

function isAllowedHost(host, port) {
    if (typeof host !== 'string' || !host) return false;

    const expected = [
        `localhost:${port}`,
        `127.0.0.1:${port}`
    ];

    return expected.includes(host.toLowerCase());
}

function isAllowedOrigin(origin, port) {
    // Origin e opcional (mesma origem em GET normalmente nao envia).
    if (origin === undefined) return true;
    if (typeof origin !== 'string') return false;

    const expected = [
        `https://localhost:${port}`,
        `https://127.0.0.1:${port}`
    ];

    return expected.includes(origin.toLowerCase());
}

// Comparacao em tempo constante. Comparamos os digests SHA-256
// para que o tempo nao dependa do comprimento nem do conteudo.
function safeEqual(provided, expected) {
    if (typeof provided !== 'string' || typeof expected !== 'string') {
        return false;
    }

    if (provided.length !== expected.length) {
        return false;
    }

    const providedDigest = crypto.createHash('sha256').update(provided).digest();
    const expectedDigest = crypto.createHash('sha256').update(expected).digest();

    return crypto.timingSafeEqual(providedDigest, expectedDigest);
}

function resetFailures() {
    failureCount = 0;
    failureWindowStart = Date.now();
}

function isFailureWindowExpired() {
    return Date.now() - failureWindowStart > AUTH_FAILURE_WINDOW;
}

function registerFailure() {
    if (isFailureWindowExpired()) {
        failureCount = 0;
        failureWindowStart = Date.now();
    }

    failureCount += 1;

    return failureCount > AUTH_MAX_FAILURES;
}

function deny() {
    const shouldDestroy = registerFailure();

    return { allowed: false, destroy: shouldDestroy };
}

function authorizeHttp(req, options = {}) {
    const port = options.port;

    // Sem contexto inicializado ou sem porta: nega tudo.
    if (!initialized || !port) {
        return { allowed: false, destroy: false };
    }

    if (isFailureWindowExpired()) {
        resetFailures();
    }

    const headers = req.headers || {};

    // Host exato do nosso servidor (bloqueia DNS rebinding)
    if (!isAllowedHost(headers.host, port)) {
        return deny();
    }

    // Origin, quando presente, tem de ser a nossa
    if (!isAllowedOrigin(headers.origin, port)) {
        return deny();
    }

    // Requisicao iniciada por outro site no navegador
    const fetchSite = headers['sec-fetch-site'];

    if (fetchSite === 'cross-site' || fetchSite === 'same-site') {
        return deny();
    }

    // Header de autenticacao: string unica, valor exato
    const provided = headers[AUTH_HEADER];

    if (!safeEqual(provided, httpKey.toString('hex'))) {
        return deny();
    }

    return { allowed: true, destroy: false };
}

// ============================================================
// PARAMETROS DE AUTENTICACAO NODE -> PHP
//
// A chave NAO trafega na requisicao: ela vive no ambiente do
// processo PHP (MIPHANT_GUARD_KEY), definido no spawn. Assim,
// um processo local que fale direto com o FPM nao consegue
// assinar a propria requisicao — teria de conhecer a chave do
// nosso processo.
//
// A MAC cobre metodo, caminho, query e tamanho/tipo do corpo.
// O corpo fica fora da MAC de proposito: consumir php://input no
// auto_prepend quebraria multipart/form-data e $_POST.
// ============================================================

function createPhpAuthParams(cgiParams) {
    if (!initialized) {
        return {};
    }

    const timestamp = String(Math.floor(Date.now() / 1000));
    const nonce = crypto.randomBytes(16).toString('hex');

    const canonical = [
        cgiParams.REQUEST_METHOD ?? '',
        cgiParams.SCRIPT_NAME ?? '',
        cgiParams.REQUEST_URI ?? '',
        cgiParams.QUERY_STRING ?? '',
        cgiParams.CONTENT_LENGTH ?? '',
        cgiParams.CONTENT_TYPE ?? '',
        timestamp,
        nonce
    ].join('\n');

    const auth = crypto
        .createHmac('sha256', phpKey)
        .update(canonical, 'utf8')
        .digest('base64');

    return {
        MIPHANT_PHP_TS: timestamp,
        MIPHANT_PHP_NONCE: nonce,
        MIPHANT_PHP_AUTH: auth
    };
}

// ============================================================
// EXPORTS
// ============================================================

module.exports = {
    AUTH_CLOCK_SKEW,
    create,
    isActive,
    getHttpAuthHeader,
    getPhpKeyHex,
    getFingerprint,
    authorizeHttp,
    createPhpAuthParams,
    wipe
};
