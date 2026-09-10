<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

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