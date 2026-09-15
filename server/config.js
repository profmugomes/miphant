// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

'use strict';

/**
 * Configuracao centralizada do MiPhant Server
 */

const SERVER_CONFIG = {
    // Host para todas as conexoes locais
    HOST: '127.0.0.1',

    // Porta padrao do servidor HTTPS
    DEFAULT_HTTPS_PORT: 8443,

    // Tamanho maximo do body (50MB)
    MAX_BODY_SIZE: 50 * 1024 * 1024,

    // Timeout para operacoes PHP (30s)
    PHP_TIMEOUT: 30000,

    // Tamanho maximo da resposta PHP (50MB)
    PHP_MAX_RESPONSE_SIZE: 50 * 1024 * 1024
};

module.exports = SERVER_CONFIG;
