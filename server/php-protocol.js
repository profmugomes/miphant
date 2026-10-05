// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

'use strict';

const net = require('net');
const {
    PHP_TIMEOUT,
    PHP_STREAM_IDLE_TIMEOUT,
    PHP_STREAM_MAX_DURATION,
    PHP_MAX_RESPONSE_SIZE,
    PHP_MAX_STDERR_SIZE
} = require('./config');

// ============================================================
// PHP PROTOCOL CONSTANTS
// ============================================================

const PHP_PROTOCOL_VERSION_1 = 1;

const PHP_PROTOCOL_BEGIN_REQUEST = 1;
const PHP_PROTOCOL_END_REQUEST = 3;
const PHP_PROTOCOL_PARAMS = 4;
const PHP_PROTOCOL_STDIN = 5;
const PHP_PROTOCOL_STDOUT = 6;
const PHP_PROTOCOL_STDERR = 7;

const PHP_PROTOCOL_RESPONDER = 1;
const PHP_PROTOCOL_KEEP_CONN = 0;

// ============================================================
// REQUEST ID INCREMENTAL (BUG FIX)
// ============================================================

let nextRequestId = 1;

// ============================================================
// CRIAR REGISTRO PHP PROTOCOL
// ============================================================

function createPhpRecord(type, requestId, content = Buffer.alloc(0)) {
    const padding = (8 - (content.length % 8)) % 8;
    const header = Buffer.alloc(8);

    header.writeUInt8(PHP_PROTOCOL_VERSION_1, 0);
    header.writeUInt8(type, 1);
    header.writeUInt16BE(requestId, 2);
    header.writeUInt16BE(content.length, 4);
    header.writeUInt8(padding, 6);
    header.writeUInt8(0, 7);

    return Buffer.concat([header, content, Buffer.alloc(padding)]);
}

// ============================================================
// CODIFICAR COMPRIMENTO PHP PROTOCOL
// ============================================================

function encodePhpLength(length) {
    if (length < 128) {
        return Buffer.from([length]);
    }

    const buffer = Buffer.alloc(4);
    buffer.writeUInt32BE((length | 0x80000000) >>> 0, 0);
    return buffer;
}

// ============================================================
// CODIFICAR PARAMETROS PHP PROTOCOL
// ============================================================

function encodePhpParams(params) {
    const buffers = [];

    for (const [name, value] of Object.entries(params)) {
        const nameBuffer = Buffer.from(String(name), 'utf8');
        const valueBuffer = Buffer.from(String(value ?? ''), 'utf8');

        buffers.push(encodePhpLength(nameBuffer.length));
        buffers.push(encodePhpLength(valueBuffer.length));
        buffers.push(nameBuffer);
        buffers.push(valueBuffer);
    }

    return Buffer.concat(buffers);
}

// ============================================================
// EXECUTAR REQUISICAO PHP PROTOCOL
//
// Correcoes:
//   - requestId incremental (bug fix)
//   - PHP_PROTOCOL_KEEP_CONN = 0 (bug fix)
//   - timeout no socket (bug fix)
//   - PHP_MAX_RESPONSE_SIZE (bug fix)
//
// Streaming (opcional):
//   - onStdout: callback chamado a cada registro FCGI_STDOUT.
//     Quando presente, os chunks NAO sao acumulados em memoria
//     (stdout resolvido = Buffer vazio) e o timeout passa a ser
//     inatividade (PHP_STREAM_IDLE_TIMEOUT) + teto absoluto
//     (PHP_STREAM_MAX_DURATION).
//   - signal: AbortSignal para cancelar quando o cliente
//     desconectar no meio da resposta.
// ============================================================

function executePhpProtocol({ host, port, params, body, onStdout, onBackpressure, signal }) {
    return new Promise((resolve, reject) => {
        const socket = new net.Socket();
        const requestId = nextRequestId++;

        // FastCGI usa requestId de 16 bits (0..65535)
        if (nextRequestId > 65535) {
            nextRequestId = 1;
        }

        const streaming = typeof onStdout === 'function';
        const stdoutChunks = [];
        const stderrChunks = [];

        let incoming = Buffer.alloc(0);
        let completed = false;
        let totalResponseSize = 0;
        let stderrSize = 0;

        // Timeout total (modo legado) ou inatividade + teto (modo stream)
        const timer = setTimeout(() => {
            fail(new Error(streaming
                ? 'PHP stream max duration exceeded'
                : 'PHP protocol timeout'));
        }, streaming ? PHP_STREAM_MAX_DURATION : PHP_TIMEOUT);

        let idleTimer = null;

        function resetIdleTimer() {
            if (!streaming) return;

            clearTimeout(idleTimer);
            idleTimer = setTimeout(() => {
                fail(new Error('PHP stream idle timeout'));
            }, PHP_STREAM_IDLE_TIMEOUT);
        }

        if (streaming) {
            resetIdleTimer();
        }

        // Abort do cliente (fetch cancelado / janela fechada)
        function onAbort() {
            const error = new Error('PHP request aborted');
            error.code = 'ABORTED';
            fail(error);
        }

        if (signal) {
            if (signal.aborted) {
                onAbort();
                return;
            }
            signal.addEventListener('abort', onAbort, { once: true });
        }

        function cleanup() {
            clearTimeout(timer);
            clearTimeout(idleTimer);

            if (signal) {
                signal.removeEventListener('abort', onAbort);
            }

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

            const stdout = streaming ? Buffer.alloc(0) : Buffer.concat(stdoutChunks);
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

                if (version !== PHP_PROTOCOL_VERSION_1) continue;
                if (id !== requestId) continue;

                if (type === PHP_PROTOCOL_STDOUT) {
                    if (content.length) {
                        totalResponseSize += content.length;

                        if (totalResponseSize > PHP_MAX_RESPONSE_SIZE) {
                            fail(new Error('PHP response too large'));
                            return;
                        }

                        if (streaming) {
                            // Excecao no callback NAO pode escapar para o
                            // event loop (viraria uncaughtException sem
                            // fail() — requisicao pendurada ate o timeout)
                            try {
                                const wantMore = onStdout(Buffer.from(content));

                                // Backpressure: consumidor lotado => pausa
                                if (wantMore === false && !socket.paused) {
                                    socket.pause();
                                    if (typeof onBackpressure === 'function') {
                                        onBackpressure(() => {
                                            if (!completed && !socket.destroyed) {
                                                socket.resume();
                                            }
                                        });
                                    }
                                }
                            } catch (error) {
                                fail(error);
                                return;
                            }
                        } else {
                            stdoutChunks.push(Buffer.from(content));
                        }
                    }
                } else if (type === PHP_PROTOCOL_STDERR) {
                    // Limite defensivo: warnings acumulados num stream
                    // de ate 310s nao devem crescer sem teto
                    if (content.length && stderrSize < PHP_MAX_STDERR_SIZE) {
                        const slice = content.subarray(
                            0, PHP_MAX_STDERR_SIZE - stderrSize
                        );
                        stderrChunks.push(Buffer.from(slice));
                        stderrSize += slice.length;
                    }
                } else if (type === PHP_PROTOCOL_END_REQUEST) {
                    complete();
                    return;
                }
            }
        }

        socket.on('data', chunk => {
            resetIdleTimer();
            incoming = Buffer.concat([incoming, chunk]);
            parseRecords();
        });

        socket.on('error', error => {
            fail(error);
        });

        socket.on('timeout', () => {
            fail(new Error('PHP protocol socket timeout'));
        });

        socket.connect(port, host, () => {
            // BEGIN_REQUEST
            const begin = Buffer.alloc(8);
            begin.writeUInt16BE(PHP_PROTOCOL_RESPONDER, 0);
            begin.writeUInt8(PHP_PROTOCOL_KEEP_CONN, 2);

            socket.write(createPhpRecord(PHP_PROTOCOL_BEGIN_REQUEST, requestId, begin));

            // PARAMS
            const paramsBuffer = encodePhpParams(params);
            const PARAMS_MAX = 65535;

            for (let offset = 0; offset < paramsBuffer.length; offset += PARAMS_MAX) {
                const slice = paramsBuffer.subarray(
                    offset,
                    Math.min(offset + PARAMS_MAX, paramsBuffer.length)
                );
                socket.write(createPhpRecord(PHP_PROTOCOL_PARAMS, requestId, slice));
            }

            socket.write(createPhpRecord(PHP_PROTOCOL_PARAMS, requestId));

            // STDIN
            if (body && body.length > 0) {
                for (let offset = 0; offset < body.length; offset += PARAMS_MAX) {
                    const slice = body.subarray(
                        offset,
                        Math.min(offset + PARAMS_MAX, body.length)
                    );
                    socket.write(createPhpRecord(PHP_PROTOCOL_STDIN, requestId, slice));
                }
            }

            socket.write(createPhpRecord(PHP_PROTOCOL_STDIN, requestId));
        });
    });
}

// ============================================================
// PARSING DE BLOCO DE CABECALHO CGI (compartilhado entre
// parsePhpResponse e o parser incremental)
// ============================================================

function parseHeaderBlock(headerText, separator) {
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

    return { status, headers };
}

// Localiza o delimitador de fim de cabecalho (\r\n\r\n ou \n\n)
// em um buffer. Retorna { position, separatorLength } ou null.
function findHeaderDelimiter(buffer) {
    const str = buffer.toString('utf8');

    const crlfPos = str.indexOf('\r\n\r\n');
    const lfPos = str.indexOf('\n\n');

    let position = -1;

    if (crlfPos !== -1 && (lfPos === -1 || crlfPos < lfPos)) {
        position = crlfPos;
    } else if (lfPos !== -1) {
        position = lfPos;
    }

    if (position === -1) {
        return null;
    }

    const isCrlf = str.charAt(position) === '\r';
    return { position, separatorLength: isCrlf ? 4 : 2, separator: isCrlf ? '\r\n' : '\n' };
}

// ============================================================
// PARSER INCREMENTAL DE RESPOSTA PHP
//
// Consumido pelo servidor HTTP em modo streaming: aceita chunks
// parciais de FCGI_STDOUT e emite eventos:
//   { type: 'headers', status, headers }  (uma unica vez)
//   { type: 'body', data: Buffer }        (0..N vezes)
//
// Os cabecalhos so sao emitidos quando o delimitador completo
// (\r\n\r\n ou \n\n) foi encontrado — nunca "prematuramente".
// ============================================================

function createPhpResponseParser() {
    let pending = Buffer.alloc(0);
    let headersEmitted = false;

    return {
        push(chunk) {
            const events = [];

            if (headersEmitted) {
                if (chunk.length) {
                    events.push({ type: 'body', data: chunk });
                }
                return events;
            }

            pending = Buffer.concat([pending, chunk]);

            const delimiter = findHeaderDelimiter(pending);

            if (!delimiter) {
                // Cabecalho ainda incompleto — acumula
                return events;
            }

            const headerBuffer = pending.subarray(0, delimiter.position);
            const body = pending.subarray(delimiter.position + delimiter.separatorLength);

            const { status, headers } = parseHeaderBlock(
                headerBuffer.toString('utf8'),
                delimiter.separator
            );

            pending = Buffer.alloc(0);
            headersEmitted = true;

            events.push({ type: 'headers', status, headers });

            if (body.length) {
                events.push({ type: 'body', data: body });
            }

            return events;
        },

        complete() {
            const events = [];

            if (!headersEmitted) {
                // Sem delimitador ate o fim: fallback identico a
                // parsePhpResponse (status 200 + content-type padrao)
                events.push({
                    type: 'headers',
                    status: 200,
                    headers: { 'Content-Type': 'text/html; charset=utf-8' }
                });

                if (pending.length) {
                    events.push({ type: 'body', data: pending });
                }

                pending = Buffer.alloc(0);
                headersEmitted = true;
            }

            return events;
        }
    };
}

// ============================================================
// PARSEAR RESPOSTA PHP (modo legado: buffer unico)
// ============================================================

function parsePhpResponse(buffer) {
    const delimiter = findHeaderDelimiter(buffer);

    if (!delimiter) {
        return {
            status: 200,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
            body: buffer
        };
    }

    const headerBuffer = buffer.subarray(0, delimiter.position);
    const body = buffer.subarray(delimiter.position + delimiter.separatorLength);

    const { status, headers } = parseHeaderBlock(
        headerBuffer.toString('utf8'),
        delimiter.separator
    );

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

function sanitizeHeaders(headers, { streaming = false } = {}) {
    const result = {};

    for (const [name, value] of Object.entries(headers)) {
        if (FORBIDDEN_HEADERS.has(name.toLowerCase())) {
            continue;
        }

        // Em modo stream o total e desconhecido: manter um
        // content-length vindo do PHP causaria
        // ERR_HTTP_CONTENT_LENGTH_MISMATCH no Node.
        if (streaming && name.toLowerCase() === 'content-length') {
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
    executePhpProtocol,
    parsePhpResponse,
    createPhpResponseParser,
    sanitizeHeaders
};
