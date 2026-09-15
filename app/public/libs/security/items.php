<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

namespace MiPhantLibs\security;

class items {
    public function clean(?string $valor) : string|int|null {
        if (is_null($valor)) {
            $txt = '';
        } else {
            $txt = trim($valor);
            $txt = strip_tags($txt);
            $txt = addslashes($txt);
        }

        return $txt;
    }
}