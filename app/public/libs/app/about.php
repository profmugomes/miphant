<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

namespace MiPhantLibs\app;

use MiPhantLibs\langs\translate;
use MiPhantLibs\system\server;

class about
{
    private mixed $traduzir;

    public function __construct()
    {
        $this->traduzir = new translate();
    }

    public function setLicense(string $name, string $text): string
    {
        $safeText = nl2br(str_replace(['<', '>', ' '], ['&lt;', '&gt;', '&nbsp;'], $text));

        $txt = '<button class="collapsible" type="button">'
            . htmlspecialchars($name) . ' (' . $this->traduzir->get('See license') . ')'
            . '</button>';

        $txt .= '<div class="content">' . $safeText . '</div>';

        return $txt;
    }

    public function show($text = '')
    {
        $config = new config();
        $txt = '<h1>' . $this->traduzir->get('About %s', $config->get('app', 'name')) . '</h1>';
        $txt .= '<p>' . htmlspecialchars($config->get('app', 'name') . ' ' . $config->get('app', 'version')) . '</p>';
        $txt .= '<p>' . $this->traduzir->get('Developed by: %s', $config->get('app', 'author', 'name')) . '</p>';
        if (!empty($config->get('app', 'author', 'organization'))) {
            $txt .= '<p>' . $this->traduzir->get('Organization: %s', $config->get('app', 'author', 'organization')) . '</p>';
        }
        $txt .= '<p>Site: <a href="javascript:miphant.openURL(\''
            . htmlspecialchars($config->get('app', 'homepage')) . '\');">'
            . htmlspecialchars($config->get('app', 'homepage')) . '</a></p>';
        $txt .= '<p>' . htmlspecialchars($config->get('app', 'copyright')) . '</p>';
        $txt .= '<p>' . $this->traduzir->get('License: %s', $config->get('app', 'license')) . '</p>';
        $txt .= '<hr class="border border-primary border-3 opacity-75">';
        $txt .= '<h3>' . $this->traduzir->get('Recursos de Terceiros Utilizados') . '</h3>';

        $file = new file();
        $server = new server();
        $base = dirname($server->documentroot(), 2);
        $phpLicense = $base . '/php/LICENSE';

        $txt .= $this->setLicense('MiPhant', $file->open($base . '/LICENSE.md'));
        $txt .= $this->setLicense('MiPhantLibs', $file->open($base . '/LICENSE.md'));

        $electronCandidates = [
            dirname($base) . '/LICENSE.electron.txt',
            dirname($base) . '/LICENSE',
            $base . '/LICENSE.electron.txt',
        ];
        foreach ($electronCandidates as $el) {
            if (file_exists($el)) {
                $txt .= $this->setLicense('Electron', $file->open($el));
                break;
            }
        }

        if (file_exists($phpLicense)) {
            $txt .= $this->setLicense('PHP', $file->open($phpLicense));
        }

        $txt .= $text;

        $txt .= "<script>
        var col1 = document.getElementsByClassName('collapsible');
        var i;

        for (i = 0; i < col1.length; i++) {
            col1[i].addEventListener('click', function () {
                this.classList.toggle('active');
                var content = this.nextElementSibling;
                if (content.style.display === 'block') {
                    content.style.display = 'none';
                } else {
                    content.style.display = 'block';
                }
            });
        }
        </script>";

        echo $txt;
    }
}
