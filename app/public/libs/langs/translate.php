<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

namespace MiPhantLibs\langs;

use MiPhantLibs\system\server;

class translate
{
    private array $miLang = [];

    public function __construct()
    {
        $server = new server();
        $lang = $_ENV['MIPHANT_LANG'] ?? 'en';
        $langsDir = dirname($server->documentroot()) . '/langs';

        // Cadeia de fallback: "pt-br" → pt-br.json → pt.json → en.json
        $candidates = [$lang];
        if (str_contains($lang, '-')) {
            $candidates[] = explode('-', $lang)[0];
        }
        $candidates[] = 'en';

        foreach ($candidates as $candidate) {
            $path = $langsDir . '/' . $candidate . '.json';
            if (file_exists($path)) {
                $this->miLang = json_decode(file_get_contents($path), true) ?? [];
                break;
            }
        }
    }

    public function get(string $text, string ...$values): string
    {
        return (empty($this->miLang[$text])) ? sprintf($text, ...$values) : sprintf($this->miLang[$text], ...$values);
    }
}
