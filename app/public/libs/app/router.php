<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

namespace MiPhantLibs\app;

use MiPhantLibs\system\server;

class router
{
    private bool $isFile = false;
    private bool $sNoPHP = false;
    private bool $encontrado = false;
    private string $url = '';
    private mixed $path;
    private mixed $arquivo;

    public function __construct()
    {
        $server = new server();
        $this->path = new path();
        $this->arquivo = new file();
        $this->url = rtrim($server->uri());

        if (!empty($this->url)) {
            if ($this->url !== '/') {
                if (file_exists($this->path->join($server->documentroot(), $this->url))) {
                    if ($this->arquivo->checkExtension($this->url, 'php')) {
                        include_once($this->path->join($server->documentroot(), $this->url));
                    }
                    $this->isFile = true;
                }
            }
        }
    }

    public function __toString()
    {
        return $this->encontrado;
    }

    public function noPHP()
    {
        return $this->sNoPHP;
    }

    public function get(string $path, mixed $function = false): bool
    {
        if (!$this->isFile) {
            $sFilename = rtrim(ltrim($path, '/'), '/');
            $sURL = rtrim($this->url, '/');

            if ($sURL == $sFilename) {
                if (!$this->encontrado) {
                    $this->encontrado = true;
                    if (!is_bool($function)) {
                        $function();
                    }
                    return true;
                } else {
                    return false;
                }
            } else {
                if (!$this->encontrado) {
                    if ($path == '404') {
                        if (!is_bool($function)) {
                            $function();
                        }

                        return true;
                    }
                }

                return false;
            }
        } else {
            if ($this->arquivo->checkExtension($this->url, 'php')) {
                return true;
            } else {
                $this->sNoPHP = true;
                return false;
            }
        }
    }
}
