<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

namespace MiPhantLibs\app;

class folder {
    private mixed $caminho;

    public function __construct()
    {
        $this->caminho = new path();
    }

    public function create(string $directory, int $permission = 0777, bool $recursive = true): bool {
        return mkdir($directory, $permission, $recursive);
    }

    private function excluirRecursivamente(string $diretorio): bool {
        $arquivos = scandir($diretorio);

        foreach ($arquivos as $arquivo) {
            if ($arquivo !== '.' && $arquivo !== '..') {
                $completo = $diretorio . DIRECTORY_SEPARATOR . $arquivo;
                if (is_dir($completo)) {
                    $this->excluirRecursivamente($completo);
                } else {
                    unlink($completo);
                }
            }
        }

        return rmdir($diretorio);
    }

    public function remove(string $directory, bool $recursive = false) {
        return ($recursive) ? $this->excluirRecursivamente($directory) : rmdir($directory);
    }

    public function exists(string $name): bool {
        return file_exists($name);
    }
}