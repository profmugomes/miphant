<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

namespace MiPhantLibs\app;

use MiPhantLibs\system\platform;

class path {
    public function join(string ...$values):string {
        return implode(DIRECTORY_SEPARATOR, array_map(function($v) {
            return trim($v, '/\\');
        }, $values));
    }
}