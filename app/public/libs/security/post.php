<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

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
