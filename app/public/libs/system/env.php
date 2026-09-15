<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

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