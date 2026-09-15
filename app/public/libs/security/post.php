<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

namespace MiPhantLibs\security;

class post
{
    public function get(string $name, int $filter = FILTER_DEFAULT): string|int|null
    {
        return filter_input(INPUT_POST, $name, $filter);
    }

    public function exists(string $name, array|int $options = 0): bool
    {
        return (empty(filter_input(INPUT_POST, $name, FILTER_DEFAULT, $options))) ? false : true;
    }

    public function request(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] == 'POST') ? true : false;
    }
}
