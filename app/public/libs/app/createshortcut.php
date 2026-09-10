<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

namespace MiPhantLibs\app;

use MiPhantLibs\langs\translate;
use MiPhantLibs\system\env;
use MiPhantLibs\system\platform;
use MiPhantLibs\system\server;

class createshortcut {
    //AudioVideo, Audio, Video, Development, Education, Graphics, Network, Office, Science, Settings, System, Utility

    public function create() {
        $platform = new platform();
        $env = new env();
        $translate = new translate();
        $server = new server();
        $script = new functions();
        $config = new config();

        if ($platform->osLinux()) {
            $sFolder = $env->homeDir() . '/.local/share/applications/';
            if (file_exists($sFolder)) {
                $tplShortcut = sprintf('[Desktop Entry]
Version=%s
Name=%s
Comment=%s
Type=Application
Exec=%s
Icon=%s
Categories=%s', $config->get('app', 'version'), $config->get('app', 'name'), $config->get('app', 'description'), str_replace('/resources/app', '', $server->documentroot()) . '/' . $config->get('app', 'name'), $server->documentroot() . '/icon/' . $config->get('app', 'icon'), $config->get('app', 'categories'));

                $sCreateFile = file_put_contents($sFolder . '/' . $config->get('app', 'id') . '.desktop', $tplShortcut);
                if ($sCreateFile) {
                    $script->alert($translate->get('Information %s', $config->get('app', 'name')), $translate->get('Shortcut created in the Start menu.'), 'info');
                } else {
                    $script->alert($translate->get('Information %s', $config->get('app', 'name')), $translate->get('It was not possible to create the shortcut in the Start menu!'), 'error');
                }
            } else {
                $script->alert($translate->get('Information %s', $config->get('app', 'name')), $translate->get('It was not possible to create the shortcut in the Start menu!'), 'error');
            }
        } else {
            $script->alert($translate->get('Informação %s', $config->get('app', 'name')), $translate->get('In Windows you can create a shortcut by right clicking on the executable "%s.exe" and clicking "Create shortcut"', $config->get('app', 'id')), 'error');
        }

        $script->closeWindow();
    }
}