<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

namespace MiPhantLibs\app;

class datetime {
    public function date():string {
        return date('d/m/Y');
    }

    public function now(): string {
        return date('d/m/Y H:i:s');
    }

    public function change(string $date, string $format = 'd/m/Y', string $newformat = 'Y-m-d'): string {
        $data = \DateTime::createFromFormat($format, $date);
        $txt = $data->format($newformat);
        return $txt;
    }

    public function dayOfTheWeek(string $date): string {
        $diasemana = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $valor = date('w', strtotime($date));
        return $diasemana[$valor];
    }

    public function monthInFull(int $mes):string {
        $meses = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $valor = ltrim($mes, '0');
        return $meses[$valor];
    }
}