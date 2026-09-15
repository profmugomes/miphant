<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

namespace MiPhantLibs\app;

use MiPhantLibs\langs\translate;

class functions
{
    private mixed $lang;
    private bool $sSemTag = false;

    public function __construct()
    {
        $this->lang = new translate();
    }

    private function clean()
    {
        $this->sSemTag = false;
    }

    public function noTag()
    {
        $this->sSemTag = true;
        return $this;
    }

    public function alert(string $title, string $message, string $type)
    {
        if (!$this->sSemTag) {
            echo '<script>';
        }

        printf("miphant.alert('%s', '%s', '%s', '%s');", $title, $message, $type, $this->lang->get('Continue'));

        if (!$this->sSemTag) {
            echo '</script>';
        }

        $this->clean();
    }

    public function confirm(string $title, string $message, string $type, mixed $functionContinue, mixed $functionCancel)
    {
        if (!$this->sSemTag) {
            echo '<script>';
        }

        echo "miphant.confirm('{$title}', '{$message}', '$type', '" . $this->lang->get('Continue') . "', '" . $this->lang->get('Cancel') . "').then((result) => {
                if (result == 1) {
                    " . $functionCancel() . "
                } else {
                    " . $functionContinue() . "
                }
            });";

        if (!$this->sSemTag) {
            echo '</script>';
        }

        $this->clean();
    }

    public function redirect(string $url, mixed $params = '')
    {
        $sParams = '?';

        if (is_array($params)) {
            foreach ($params as $name => $value) {
                $sParams .= sprintf('%s=%s&', $name, $value);
            }
        } else {
            $sParams = '';
        }

        $sParams = rtrim($sParams, '&');

        if (!$this->sSemTag) {
            echo '<script>';
        }

        printf("window.location.assign('%s%s');", $url, $sParams);

        if (!$this->sSemTag) {
            echo '</script>';
        }

        $this->clean();
    }

    public function openURL(string $url)
    {
        if (!$this->sSemTag) {
            echo '<script>';
        }

        echo "miphant.openURL('$url');";

        if (!$this->sSemTag) {
            echo '</script>';
        }

        $this->clean();
    }

    public function newWindow(string $url, int $width = 800, int $height = 600, bool $resizable = true, bool $frame = true, bool $hide = false, string $menu = 'menu') {
        $sResizable = ($resizable) ? 'true': 'false';
        $sFrame = ($frame) ? 'true' : 'false';
        $sHide = ($hide) ? 'true' : 'false';

        if (!$this->sSemTag) {
            echo '<script>';
        }

        echo "miphant.newWindow('$url', $width, $height, $sResizable, $sFrame, $sHide, '$menu');";

        if (!$this->sSemTag) {
            echo '</script>';
        }

        $this->clean();
    }

    public function closeWindow() {
        if (!$this->sSemTag) {
            echo '<script>';
        }

        echo 'window.close();';

        if (!$this->sSemTag) {
            echo '</script>';
        }

        $this->clean();
    }

    public function notification(string $title, string $message) {
        if (!$this->sSemTag) {
            echo '<script>';
        }

        printf("miphant.notification('%s','%s');", $title, $message);

        if (!$this->sSemTag) {
            echo '</script>';
        }

        $this->clean();
    }

    public function tray(string $title, string $tooltip, string $icon, array $menus) {
        $sItems = '{';
        if (!empty($menus)) {
            foreach ($menus as $item) {
                if (empty($item['page']))  {
                    $txt = "script: '{$item['script']}'";
                } else {
                    $txt = "page: '{$item['page']}'";
                    if (!empty($item['newwindow'])) {
                        $txt .= ", newwindow: {$item['newwindow']}";
                    }
                }
                $sItems .= sprintf('"%s": { %s },', $item['label'], $txt);
            }
            $sItems = rtrim($sItems, ',') . '}';

            echo $sItems;
        }
        
        if (!$this->sSemTag) {
            echo '<script>';
        }

        printf("miphant.tray('%s','%s','%s', JSON.stringify(%s));", $title, $tooltip, $icon, $sItems);

        if (!$this->sSemTag) {
            echo '</script>';
        }

        $this->clean();
    }
}
