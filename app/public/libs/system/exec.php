<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

namespace MiPhantLibs\system;

class exec {
    private mixed $sProcess;
    private mixed $sPipes;
    private array $sCommand = [];

    public function command(string $value) {
        $this->sCommand[] = $value;
        return $this;
    }

    public function run() {
        $platform = new platform();
        
        $comando = implode(' && ', $this->sCommand);

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w']
        ];

        flush();

        if ($platform->osLinux()) {
            $this->sProcess = proc_open($comando, $descriptorspec, $this->sPipes, realpath('./'), []);
        } else {
            $this->sProcess = proc_open($comando, $descriptorspec, $this->sPipes, null, null);
        }
    }

    public function values():mixed {
        if (is_resource($this->sProcess)) {
            return fgets($this->sPipes[1]);
        } else {
            return false;
        }
    }

    public function clean() {
        flush();
    }

    public function close() {
        proc_close($this->sProcess);
    }
}