// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

'use strict';

// ============================================================
// LOGGER CENTRALIZADO
//
// Logs condicionais baseados em config.dev.tools
// - log(): só aparece em modo dev
// - error(): sempre aparece
// - warn(): sempre aparece
// ============================================================

let debugEnabled = false;

function setDebugEnabled(enabled) {
    debugEnabled = !!enabled;
}

function log(message, ...args) {
    if (debugEnabled) {
        console.log(message, ...args);
    }
}

function error(message, ...args) {
    console.error(message, ...args);
}

function warn(message, ...args) {
    console.warn(message, ...args);
}

module.exports = { setDebugEnabled, log, error, warn };
