<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

namespace MiPhantLibs\app;

class numbers {
    public function currencyReal(string $value):string {
        return number_format($value, 2, ',', '.');
    }

    public function format(string $value): string {
        return (empty($value) ? 0 : str_pad($value, 2, '0', STR_PAD_LEFT));
    }
}