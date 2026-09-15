<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

namespace MiPhantLibs\app;

use MiPhantLibs\system\platform;

class path {
    public function join(string ...$values):string {
        return implode(DIRECTORY_SEPARATOR, array_map(function($v) {
            return trim($v, '/\\');
        }, $values));
    }
}