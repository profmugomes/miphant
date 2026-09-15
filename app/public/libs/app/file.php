<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

namespace MiPhantLibs\app;

class file
{
    public function checkExtension(string $filename, string $ext = ''): bool
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (empty($ext)) {
            return (empty($extension)) ? false : true;
        } else {
            return ($ext == $extension) ? true : false;
        }
    }

    public function exists(string $name): bool
    {
        return file_exists($name);
    }

    public function create(string $name): bool
    {
        return touch($name);
    }

    public function open(string $filename): string|bool
    {
        return file_get_contents($filename);
    }

    public function save(string $filename, string $text, bool $replace = true): int|bool
    {
        return ($replace) ? file_put_contents($filename, $text) : file_put_contents($filename, $text, FILE_APPEND);
    }

    public function remove(string $filename)
    {
        return unlink($filename);
    }
}
