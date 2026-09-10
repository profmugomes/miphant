<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

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
                if (is_dir($this->caminho->join($diretorio . '/' . $arquivo))) {
                    $this->excluirRecursivamente($this->caminho->join($diretorio . '/' . $arquivo) . '/');                    
                } else {
                    unlink($this->caminho->join($diretorio . '/' . $arquivo));
                }
            }
        }

        return rmdir($this->caminho->join($diretorio . '/'));
    }

    public function remove(string $directory, bool $recursive = false) {
        return ($recursive) ? $this->excluirRecursivamente($directory) : rmdir($directory);
    }

    public function exists(string $name): bool {
        return file_exists($name);
    }
}