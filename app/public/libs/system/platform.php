<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

namespace MiPhantLibs\system;

class platform {
    public function osLinux():bool {
        return PHP_OS_FAMILY === 'Linux';
    }

    public function osWindows():bool {
        return PHP_OS_FAMILY === 'Windows';
    }
}