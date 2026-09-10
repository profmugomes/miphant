<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

namespace MiPhantLibs\system;

class env {
    public function get(string $name):string {
        return filter_input(INPUT_ENV, $name, FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    }

    public function username():string {
        return $this->get('MIPHANT_USERNAME');
    }

    public function lang():string {
        return $this->get('MIPHANT_LANG');
    }

    public function platform():string {
        return $this->get('MIPHANT_PLATFORM');
    }

    public function homeDir():string {
        return $this->get('MIPHANT_HOMEDIR');
    }

    public function argv():string {
        return $this->get('MIPHANT_ARGV');
    }
}