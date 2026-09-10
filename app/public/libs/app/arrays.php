<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

namespace MiPhantLibs\app;

class arrays {
    private array $sVetores = [];

    public function check(string $keyword, mixed $values): bool {
        $a = is_array($values) ? $values : [$values];

        foreach ($a as $query) {
            if (strpos($keyword, $query, 0) !== false) {
                return true;
            }
        }

        return false;
    }

    public function show(array $values) {
        printf('<pre>%s</pre>', print_r($values, true));
    }

    public function setArray(array $values) {
        $this->sVetores = $values;
    }

    private function getValue($valores, string ...$nomes):mixed {
        $sValor = $valores;

        foreach ($nomes as $value) {
            $sValor = (empty($sValor[$value])) ? '' : $sValor[$value];
        }

        return $sValor;
    }

    public function getCustom(array $values = [], string ...$nomes): mixed {
        return $this->getValue($values,...$nomes);
    }

    public function get(string ...$nomes): mixed {
        return $this->get($this->sVetores, ...$nomes);
    }
}