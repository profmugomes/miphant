// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

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
    PHP_MAX_STDERR_SIZE: 1024 * 1024
};

module.exports = SERVER_CONFIG;
