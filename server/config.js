// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

'use strict';

const SERVER_CONFIG = {
    HOST: '127.0.0.1',
    DEFAULT_HTTPS_PORT: 8443,
    MAX_BODY_SIZE: 50 * 1024 * 1024,
    PHP_TIMEOUT: 30000,
    PHP_MAX_RESPONSE_SIZE: 50 * 1024 * 1024
};

module.exports = SERVER_CONFIG;
