// Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>
// SPDX-License-Identifier: MIT

'use strict';

const net = require('net');

// ============================================================
// FASTCGI CONSTANTS
// ============================================================

const FCGI_VERSION_1 = 1;

const FCGI_BEGIN_REQUEST = 1;
const FCGI_END_REQUEST = 3;
const FCGI_PARAMS = 4;
const FCGI_STDIN = 5;
const FCGI_STDOUT = 6;
const FCGI_STDERR = 7;

const FCGI_RESPONDER = 1;
const FCGI_KEEP_CONN = 0;

const FCGI_TIMEOUT = 30000;

// ============================================================
// REQUEST ID INCREMENTAL (BUG FIX)
// ============================================================

let nextRequestId = 1;

// ============================================================
// CRIAR REGISTRO FASTCGI
// ============================================================

function createFcgiRecord(type, requestId, content = Buffer.alloc(0)) {
    const padding = (8 - (content.length % 8)) % 8;
    const header = Buffer.alloc(8);

    header.writeUInt8(FCGI_VERSION_1, 0);
    header.writeUInt8(type, 1);
    header.writeUInt16BE(requestId, 2);
    header.writeUInt16BE(content.length, 4);
    header.writeUInt8(padding, 6);
    header.writeUInt8(0, 7);

    return Buffer.concat([header, content, Buffer.alloc(padding)]);
}

// ============================================================
// CODIFICAR COMPRIMENTO FASTCGI
// ============================================================

function encodeFcgiLength(length) {
    if (length < 128) {
        return Buffer.from([length]);
    }

    const buffer = Buffer.alloc(4);
    buffer.writeUInt32BE((length | 0x80000000) >>> 0, 0);
    return buffer;
}

// ============================================================
// CODIFICAR PARAMETROS FASTCGI
// ============================================================

function encodeFcgiParams(params) {
    const buffers = [];

    for (const [name, value] of Object.entries(params)) {
        const nameBuffer = Buffer.from(String(name), 'utf8');
        const valueBuffer = Buffer.from(String(value ?? ''), 'utf8');

        buffers.push(encodeFcgiLength(nameBuffer.length));
        buffers.push(encodeFcgiLength(valueBuffer.length));
        buffers.push(nameBuffer);
        buffers.push(valueBuffer);
    }

    return Buffer.concat(buffers);
}

// ============================================================
// EXECUTAR REQUISICAO FASTCGI
//
// Correcoes:
//   - requestId incremental (bug fix)
//   - FCGI_KEEP_CONN = 0 (bug fix)
//   - timeout no socket (bug fix)
//   - FCGI_RESPONSE_MAX_SIZE (bug fix)
// ============================================================

const FCGI_RESPONSE_MAX_SIZE = 50 * 1024 * 1024;

function executeFastCGI({ host, port, params, body }) {
    return new Promise((resolve, reject) => {
        const socket = new net.Socket();
        const requestId = nextRequestId++;

        const stdoutChunks = [];
        const stderrChunks = [];

        let incoming = Buffer.alloc(0);
        let completed = false;
        let totalResponseSize = 0;

        const timer = setTimeout(() => {
            fail(new Error('FastCGI timeout'));
        }, FCGI_TIMEOUT);

        function cleanup() {
            clearTimeout(timer);
            socket.removeAllListeners();

            if (!socket.destroyed) {
                socket.destroy();
            }
        }

        function fail(error) {
            if (completed) return;
            completed = true;
            cleanup();
            reject(error);
        }

        function complete() {
            if (completed) return;
            completed = true;

            const stdout = Buffer.concat(stdoutChunks);
            const stderr = Buffer.concat(stderrChunks);

            cleanup();
            resolve({ stdout, stderr });
        }

        function parseRecords() {
            while (incoming.length >= 8) {
                const version = incoming.readUInt8(0);
                const type = incoming.readUInt8(1);
                const id = incoming.readUInt16BE(2);
                const contentLength = incoming.readUInt16BE(4);
                const paddingLength = incoming.readUInt8(6);
                const total = 8 + contentLength + paddingLength;

                if (incoming.length < total) {
                    return;
                }

                const content = incoming.subarray(8, 8 + contentLength);
                incoming = Buffer.from(incoming.subarray(total));

                if (version !== FCGI_VERSION_1) continue;
                if (id !== requestId) continue;

                if (type === FCGI_STDOUT) {
                    if (content.length) {
                        totalResponseSize += content.length;

                        if (totalResponseSize > FCGI_RESPONSE_MAX_SIZE) {
                            fail(new Error('FastCGI response too large'));
                            return;
                        }

                        stdoutChunks.push(Buffer.from(content));
                    }
                } else if (type === FCGI_STDERR) {
                    if (content.length) {
                        stderrChunks.push(Buffer.from(content));
                    }
                } else if (type === FCGI_END_REQUEST) {
                    complete();
                    return;
                }
            }
        }

        socket.on('data', chunk => {
            incoming = Buffer.concat([incoming, chunk]);
            parseRecords();
        });

        socket.on('error', error => {
            fail(error);
        });

        socket.on('timeout', () => {
            fail(new Error('FastCGI socket timeout'));
        });

        socket.connect(port, host, () => {
            // BEGIN_REQUEST
            const begin = Buffer.alloc(8);
            begin.writeUInt16BE(FCGI_RESPONDER, 0);
            begin.writeUInt8(FCGI_KEEP_CONN, 2);

            socket.write(createFcgiRecord(FCGI_BEGIN_REQUEST, requestId, begin));

            // PARAMS
            const paramsBuffer = encodeFcgiParams(params);
            const PARAMS_MAX = 65535;

            for (let offset = 0; offset < paramsBuffer.length; offset += PARAMS_MAX) {
                const slice = paramsBuffer.subarray(
                    offset,
                    Math.min(offset + PARAMS_MAX, paramsBuffer.length)
                );
                socket.write(createFcgiRecord(FCGI_PARAMS, requestId, slice));
            }

            socket.write(createFcgiRecord(FCGI_PARAMS, requestId));

            // STDIN
            if (body && body.length > 0) {
                for (let offset = 0; offset < body.length; offset += PARAMS_MAX) {
                    const slice = body.subarray(
                        offset,
                        Math.min(offset + PARAMS_MAX, body.length)
                    );
                    socket.write(createFcgiRecord(FCGI_STDIN, requestId, slice));
                }
            }

            socket.write(createFcgiRecord(FCGI_STDIN, requestId));
        });
    });
}

// ============================================================
// PARSEAR RESPOSTA PHP
// ============================================================

function parsePhpResponse(buffer) {
    // Suporta \r\n\r\n e \n\n
    let position = -1;
    const str = buffer.toString('utf8');

    const crlfPos = str.indexOf('\r\n\r\n');
    const lfPos = str.indexOf('\n\n');

    if (crlfPos !== -1 && (lfPos === -1 || crlfPos < lfPos)) {
        position = crlfPos;
    } else if (lfPos !== -1) {
        position = lfPos;
    }

    if (position === -1) {
        return {
            status: 200,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
            body: buffer
        };
    }

    const headerBuffer = buffer.subarray(0, position);
    const body = buffer.subarray(position + (str.charAt(position) === '\r' ? 4 : 2));

    const headerText = headerBuffer.toString('utf8');
    const separator = str.charAt(position) === '\r' ? '\r\n' : '\n';
    const lines = headerText.split(separator);

    let status = 200;
    const headers = {};

    for (const line of lines) {
        const separatorPosition = line.indexOf(':');

        if (separatorPosition === -1) continue;

        const name = line.substring(0, separatorPosition).trim();
        const value = line.substring(separatorPosition + 1).trim();

        if (name.toLowerCase() === 'status') {
            const match = value.match(/^(\d{3})/);
            if (match) {
                status = Number(match[1]);
            }
            continue;
        }

        if (name.toLowerCase() === 'set-cookie') {
            if (!headers[name]) {
                headers[name] = [];
            }
            headers[name].push(value);
            continue;
        }

        headers[name] = value;
    }

    return { status, headers, body };
}

// ============================================================
// SANITIZAR HEADERS
// ============================================================

const FORBIDDEN_HEADERS = new Set([
    'connection',
    'keep-alive',
    'proxy-authenticate',
    'proxy-authorization',
    'te',
    'trailer',
    'transfer-encoding',
    'upgrade'
]);

function sanitizeHeaders(headers) {
    const result = {};

    for (const [name, value] of Object.entries(headers)) {
        if (FORBIDDEN_HEADERS.has(name.toLowerCase())) {
            continue;
        }
        result[name] = value;
    }

    return result;
}

// ============================================================
// EXPORTS
// ============================================================

module.exports = {
    executeFastCGI,
    parsePhpResponse,
    sanitizeHeaders
};
