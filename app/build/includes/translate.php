<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

$sText = php_sapi_name() == 'cli' ? '' : json_decode(file_get_contents(dirname(__FILE__, 3) . '/langs/' . $_ENV['MIPHANT_LANG'] . '.json'), true);
function translate($text, ...$values): string
{
    global $sText;

    if (empty($sText)) {
        $a = sprintf($text, ...$values);
    } else {
        $value = empty($sText[$text]) ? $text : $sText[$text];
        $a = sprintf($value, ...$values);
    }
    return $a;
}
