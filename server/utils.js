// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

'use strict';

const fs = require('fs');
const net = require('net');
const path = require('path');

// ============================================================
// VERIFICAR EXISTENCIA DE ARQUIVO
// ============================================================

function exists(file) {
    try {
        fs.accessSync(file);
        return true;
    } catch {
        return false;
    }
}

// ============================================================
// PAUSA ASSINCRONA
// ============================================================

function sleep(ms) {
    return new Promise(resolve => {
        setTimeout(resolve, ms);
    });
}

// ============================================================
// ENCONTRAR PORTA LIVRE (BUG FIX)
// ============================================================

function findFreePort(host) {
    return new Promise((resolve, reject) => {
        const server = net.createServer();

        server.listen(0, host, () => {
            const { port } = server.address();
            server.close(() => resolve(port));
        });

        server.on('error', reject);
    });
}

// ============================================================
// AGUARDAR PORTA
// ============================================================

function waitForPort(host, port, timeout = 10000) {
    return new Promise((resolve, reject) => {
        const started = Date.now();

        function attempt() {
            if (Date.now() - started > timeout) {
                reject(
                    new Error(
                        `Timeout aguardando ${host}:${port}`
                    )
                );
                return;
            }

            const socket = new net.Socket();
            socket.setTimeout(500);

            socket.once('connect', () => {
                socket.destroy();
                resolve();
            });

            socket.once('timeout', () => {
                socket.destroy();
                setTimeout(attempt, 100);
            });

            socket.once('error', () => {
                socket.destroy();
                setTimeout(attempt, 100);
            });

            socket.connect(port, host);
        }

        attempt();
    });
}

// ============================================================
// TIPOS MIME (constante global — não recria a cada chamada)
// ============================================================

const MIME_TYPES = {
    '.html': 'text/html; charset=utf-8',
    '.htm': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.mjs': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.xml': 'application/xml; charset=utf-8',
    '.txt': 'text/plain; charset=utf-8',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.webp': 'image/webp',
    '.ico': 'image/x-icon',
    '.pdf': 'application/pdf',
    '.wasm': 'application/wasm'
};

function getMimeType(file) {
    const extension = path.extname(file).toLowerCase();
    return MIME_TYPES[extension] || 'application/octet-stream';
}

// ============================================================
// EXPORTS
// ============================================================

module.exports = {
    exists,
    sleep,
    findFreePort,
    waitForPort,
    getMimeType,
    MIME_TYPES
};
