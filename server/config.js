// Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>
// SPDX-License-Identifier: MIT

// Site: https://www.profmugomes.com.br

'use strict';

const SERVER_CONFIG = {
    HOST: '127.0.0.1',
    DEFAULT_HTTPS_PORT: 8443,
    MAX_BODY_SIZE: 50 * 1024 * 1024,
    // Timeout total usado apenas quando executePhpProtocol e chamado
    // SEM onStdout (caminho legado). Em producao o http-server usa
    // sempre onStdout => valem PHP_STREAM_IDLE_TIMEOUT + MAX_DURATION.
    PHP_TIMEOUT: 30000,
    // Streaming: sem STDOUT/STDERR por este periodo => stream morto
    // (timer reiniciado a cada chunk recebido)
    PHP_STREAM_IDLE_TIMEOUT: 30000,
    // Streaming: teto absoluto desde a conexao, alinhado a
    // max_execution_time=300 do php/php.ini (300s + 10s de margem)
    PHP_STREAM_MAX_DURATION: 310000,
    PHP_MAX_RESPONSE_SIZE: 50 * 1024 * 1024,
    // Teto para o acumulo de stderr (warnings de scripts longos)
    PHP_MAX_STDERR_SIZE: 1024 * 1024,

    // ============================================================
    // SEGURANCA (PSK DINAMICA)
    //
    // ESTES VALORES SAO INTERNOS E FIXOS DE PROPOSITO.
    // Nao existe chave em app/config.json: a protecao nao pode
    // ser desligada nem ajustada pelo usuario do aplicativo.
    // ============================================================

    // Header injetado pelo Chromium em toda requisicao da janela
    AUTH_HEADER: 'x-miphant-auth',
    // 32 bytes = 256 bits de entropia (64 caracteres hex)
    AUTH_KEY_BYTES: 32,
    // Tolerancia do timestamp da MAC Node->PHP (segundos)
    AUTH_CLOCK_SKEW: 30,
    // Bloqueio por excesso de falhas de autenticacao
    AUTH_MAX_FAILURES: 20,
    AUTH_FAILURE_WINDOW: 10000,
    // Limite pratico de sun_path no Linux (108 bytes, com margem)
    PHP_SOCKET_MAX_PATH: 100
};

module.exports = SERVER_CONFIG;
