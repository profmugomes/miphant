<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

namespace MiPhantLibs\system;

class platform {
    public function osLinux():bool {
        return PHP_OS_FAMILY === 'Linux';
    }

    public function osWindows():bool {
        return PHP_OS_FAMILY === 'Windows';
    }
}