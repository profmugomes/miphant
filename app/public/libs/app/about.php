<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

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
        $path = new path();
        $server = new server();
        $miphantLicense = str_replace('/app', '', $server->documentroot());
        $electronLicense = str_replace('/resources/app', '', $server->documentroot());
        $phpLicense = str_replace('/app', '/php', $server->documentroot());

        $txt .= $this->setLicense('MiPhant', $file->open($path->join($miphantLicense) . '/LICENSE'));
        $txt .= $this->setLicense('MiPhantLibs', $file->open(dirname(__FILE__, 3) . '/LICENSE'));
        $txt .= $this->setLicense('Electron', $file->open($path->join($electronLicense) . '/LICENSE'));

        if (file_exists($path->join($phpLicense) . '/LICENSE')) {
            $txt .= $this->setLicense('PHP', $file->open($path->join($phpLicense) . '/LICENSE'));
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
