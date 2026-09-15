<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

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