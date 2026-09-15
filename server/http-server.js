// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

'use strict';

const https = require('https');
const crypto = require('crypto');
const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');

const { exists, findFreePort, getMimeType } = require('./utils');
const { executePhpProtocol, parsePhpResponse, sanitizeHeaders } = require('./php-protocol');
const { getPhpPort } = require('./php-manager');
const { log: loggerLog } = require('./logger');
const { HOST, DEFAULT_HTTPS_PORT, MAX_BODY_SIZE } = require('./config');

// ============================================================
// ESTADO
// ============================================================

let httpsServer = null;
let httpsPort = DEFAULT_HTTPS_PORT;
let routerEnabled = false;

// ============================================================
// CAMINHOS PUBLICOS
// ============================================================

let publicRoot = '';

function setPublicRoot(root) {
    publicRoot = root;
}

function getPublicRoot() {
    return publicRoot;
}

function setRouter(enabled) {
    routerEnabled = enabled;
}

// ============================================================
// FRONT CONTROLLER (URL AMIGAVEL)
// ============================================================

function resolveFrontController() {
    const frontController = path.join(getPublicRoot(), 'index.php');
    return exists(frontController) ? frontController : null;
}

// ============================================================
// CORS ORIGIN (BUG FIX: restrito em vez de *)
// ============================================================

function getCorsOrigin() {
    return `https://localhost:${httpsPort}`;
}

// ============================================================
// HEADERS BLOQUEADOS PARA CGI (blacklist)
//
// Seguindo o padrao de Apache/Nginx que passam todos os headers.
// Apenas headers perigosos conhecidos sao bloqueados.
// ============================================================

const CGI_HEADER_BLACKLIST = new Set([
    'proxy',
    'proxy-connection',
    'x-forwarded-for',
    'x-forwarded-host',
    'x-forwarded-proto',
    'x-real-ip'
]);

// ============================================================
// LEITURA DO BODY DA REQUISICAO
// ============================================================

function readRequestBody(req) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        let total = 0;
        let rejected = false;

        req.on('data', chunk => {
            if (rejected) return;

            total += chunk.length;

            if (total > MAX_BODY_SIZE) {
                rejected = true;
                reject(new Error('Request muito grande.'));
                req.destroy();
                return;
            }

            chunks.push(chunk);
        });

        req.on('end', () => {
            if (!rejected) {
                resolve(Buffer.concat(chunks));
            }
        });

        req.on('error', error => {
            if (!rejected) {
                rejected = true;
                reject(error);
            }
        });
    });
}

// ============================================================
// CRIAR PARAMETROS CGI
//
// BUG FIX: whitelist de headers ao inves de passar todos
// ============================================================

function createCgiParameters(req, filePath, serverPort, isRouted) {
    const host = req.headers.host || 'localhost';

    let url;
    try {
        url = new URL(req.url, `https://${host}`);
    } catch {
        url = new URL(req.url, `https://localhost`);
    }

    const params = {
        GATEWAY_INTERFACE: 'CGI/1.1',
        SERVER_SOFTWARE: 'MiPhant',
        SERVER_PROTOCOL: `HTTP/${req.httpVersion}`,
        REQUEST_METHOD: req.method,
        REQUEST_URI: req.url,
        SCRIPT_NAME: isRouted ? '/index.php' : url.pathname,
        SCRIPT_FILENAME: filePath,
        DOCUMENT_ROOT: getPublicRoot(),
        QUERY_STRING: url.searchParams.toString(),
        SERVER_NAME: host.split(':')[0],
        SERVER_PORT: String(serverPort),
        REMOTE_ADDR: req.socket.remoteAddress || HOST,
        HTTPS: 'on',
        REDIRECT_STATUS: '200'
    };

    if (req.headers['content-type']) {
        params.CONTENT_TYPE = req.headers['content-type'];
    }

    if (req.headers['content-length']) {
        params.CONTENT_LENGTH = req.headers['content-length'];
    }

    // Headers da blacklist sao bloqueados, o resto passa livremente
    for (const [name, value] of Object.entries(req.headers)) {
        if (name === 'content-type' || name === 'content-length') {
            continue;
        }

        if (CGI_HEADER_BLACKLIST.has(name.toLowerCase())) {
            continue;
        }

        const envName = 'HTTP_' + name.toUpperCase().replace(/-/g, '_');
        params[envName] = Array.isArray(value) ? value.join(', ') : String(value);
    }

    return params;
}

// ============================================================
// RESOLVER CAMINHO SEGURO
// ============================================================

function resolvePublicPath(requestPath) {
    let pathname;

    try {
        pathname = decodeURIComponent(requestPath);
    } catch {
        return null;
    }

    pathname = pathname.split('?')[0].split('#')[0];
    pathname = pathname.replace(/\\/g, '/');

    if (pathname.includes('\0')) {
        return null;
    }

    const root = path.resolve(getPublicRoot());
    let relative = pathname;

    if (relative.startsWith('/')) {
        relative = relative.substring(1);
    }

    const full = path.resolve(root, relative);

    if (full !== root && !full.startsWith(root + path.sep)) {
        return null;
    }

    return full;
}

// ============================================================
// EXECUTAR PHP
//
// BUG FIX: error.message NAO e mais exposta ao cliente
// ============================================================

async function executePhp(req, res, filePath, serverPort, isRouted) {
    try {
        const body = await readRequestBody(req);
        const params = createCgiParameters(req, filePath, serverPort, isRouted);

        const result = await executePhpProtocol({
            host: HOST,
            port: getPhpPort(),
            params,
            body
        });

        if (result.stderr.length) {
            console.error('[PHP STDERR]', result.stderr.toString('utf8'));
        }

        const phpResponse = parsePhpResponse(result.stdout);
        const headers = sanitizeHeaders(phpResponse.headers);

        res.writeHead(phpResponse.status, headers);

        if (req.method === 'HEAD') {
            res.end();
            return;
        }

        res.end(phpResponse.body);
    } catch (error) {
        console.error('[PHP REQUEST]', error);

        if (!res.headersSent) {
            res.writeHead(502, {
                'Content-Type': 'text/plain; charset=utf-8'
            });
        }

        // BUG FIX: nao expor error.message ao cliente
        res.end('PHP Server Error');
    }
}

// ============================================================
// SERVIR ARQUIVO ESTATICO
// ============================================================

async function serveStatic(req, res, filePath) {
    try {
        const stat = await fsp.stat(filePath);

        if (!stat.isFile()) {
            return false;
        }

        res.writeHead(200, {
            'Content-Type': getMimeType(filePath),
            'Content-Length': stat.size
        });

        if (req.method === 'HEAD') {
            res.end();
            return true;
        }

        const stream = fs.createReadStream(filePath);

        stream.on('error', error => {
            console.error('[STATIC]', error);

            if (!res.headersSent) {
                res.writeHead(500);
            }

            res.end();
        });

        stream.pipe(res);
        return true;
    } catch {
        return false;
    }
}

// ============================================================
// ROTEADOR HTTP
// ============================================================

async function handleRequest(req, res) {
    // CORS / OPTIONS
    if (req.method === 'OPTIONS') {
        res.writeHead(204, {
            'Access-Control-Allow-Origin': getCorsOrigin(),
            'Access-Control-Allow-Methods': 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization'
        });
        res.end();
        return;
    }

    // Parse URL
    let url;
    try {
        url = new URL(req.url, `https://${req.headers.host || 'localhost'}`);
    } catch {
        res.writeHead(400, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end('Bad Request');
        return;
    }

    let filePath = resolvePublicPath(url.pathname);

    if (!filePath) {
        res.writeHead(400, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end('Bad Request');
        return;
    }

    // Diretorio
    try {
        const stat = await fsp.stat(filePath);
      
        if (stat.isDirectory()) {
            filePath = path.join(filePath, 'index.php');

            if (!exists(filePath)) {
                const html = path.join(path.dirname(filePath), 'index.html');
                if (exists(html)) {
                    filePath = html;
                }
            }
        }
    } catch {
        // Arquivo nao existe — continua para 404
    }

    // PHP
    if (filePath.toLowerCase().endsWith('.php')) {
        if (!exists(filePath)) {
            res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
            res.end('404 - PHP file not found');
            return;
        }

        await executePhp(req, res, filePath, httpsPort);
        return;
    }

    // Estatico
    const served = await serveStatic(req, res, filePath);
    if (served) return;

    // ROUTER: fallback para front controller (URL Amigavel)
    if (routerEnabled) {
        const frontController = resolveFrontController();
        if (frontController) {
            await executePhp(req, res, frontController, httpsPort, true);
            return;
        }
    }

    // 404
    res.writeHead(404, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(`<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>404</title>
</head>
<body>
<h1>404</h1>
<p>Pagina nao encontrada.</p>
</body>
</html>
`);
}

// ============================================================
// INICIAR SERVIDOR HTTPS
// ============================================================

async function startHttpsServer(certificate) {
    validateCertificate(certificate.cert);

    const tlsOptions = {
        key: fs.readFileSync(certificate.key),
        cert: fs.readFileSync(certificate.cert)
    };

    httpsPort = await findFreePort(HOST);

    httpsServer = https.createServer(tlsOptions, (req, res) => {
        handleRequest(req, res).catch(error => {
            console.error('[HTTP]', error);

            if (!res.headersSent) {
                res.writeHead(500, {
                    'Content-Type': 'text/plain; charset=utf-8'
                });
            }

            res.end('Internal Server Error');
        });
    });

    await new Promise((resolve, reject) => {
        httpsServer.once('error', reject);
        httpsServer.listen(httpsPort, HOST, () => {
            resolve();
        });
    });

    loggerLog(`[HTTPS] https://localhost:${httpsPort}`);

    return httpsPort;
}

// ============================================================
// VALIDAR CERTIFICADO
// ============================================================

function validateCertificate(certFile) {
    try {
        const certificate = new crypto.X509Certificate(
            fs.readFileSync(certFile)
        );

        loggerLog('[HTTPS] Subject:', certificate.subject);
        loggerLog('[HTTPS] Issuer:', certificate.issuer);
        loggerLog('[HTTPS] Valid from:', certificate.validFrom);
        loggerLog('[HTTPS] Valid to:', certificate.validTo);
        loggerLog('[HTTPS] Fingerprint:', certificate.fingerprint256);

        return true;
    } catch (error) {
        console.error('[HTTPS] Certificado invalido:', error);
        return false;
    }
}

// ============================================================
// PARAR HTTPS (BUG FIX: sem double-resolve)
// ============================================================

function stopHttps() {
    if (!httpsServer) {
        return Promise.resolve();
    }

    return new Promise(resolve => {
        const server = httpsServer;
        httpsServer = null;

        let resolved = false;

        const done = () => {
            if (!resolved) {
                resolved = true;
                loggerLog('[HTTPS] Encerrado.');
                resolve();
            }
        };

        server.close(done);
        setTimeout(done, 3000);
    });
}

// ============================================================
// GETTERS
// ============================================================

function getHttpsPort() {
    return httpsPort;
}

// ============================================================
// EXPORTS
// ============================================================

module.exports = {
    startHttpsServer,
    stopHttps,
    setPublicRoot,
    getHttpsPort,
    setRouter
};
