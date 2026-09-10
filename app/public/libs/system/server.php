<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

namespace MiPhantLibs\system;

class server {
    public function domain():string {
        return sprintf('https://%s:%s', $_SERVER['SERVER_NAME'] ?? 'localhost', $_SERVER['SERVER_PORT'] ?? '8443');
    }

    public function uri():string {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $parts = explode('?', $requestUri);
        return ltrim($parts[0] ?? '', '/');
    }

    public function documentroot(): string {
        return dirname(__FILE__, 3);
    }
}