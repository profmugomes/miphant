<?php
// Copyright (C) 2025-2026 Murilo Gomes Julio
// SPDX-License-Identifier: LGPL-2.1-only

// Site: https://youtube.com/@mugomesoficial

namespace MiPhantLibs\app;

use MiPhantLibs\langs\translate;

class checkupdate
{
    private array $dados = [];

    public function url(string $value)
    {
        $this->dados['url'] = $value;
        return $this;
    }

    public function show()
    {
        $this->dados['show'] = true;
        return $this;
    }

    private function row(string ...$names): string
    {
        $values = $this->dados;

        foreach ($names as $name) {
            $values = (empty($values[$name])) ? '' : $values[$name];
        }

        return $values;
    }

    public function check()
    {
        try {
            $config = new config();
            $lang = new translate();
            $script = new functions();

            if ($this->row('show')) {
                echo $lang->get('Checking for updates...');
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->row('url'));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $html = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new \Exception($lang->get('Error fetching data: %s', curl_error($ch)));
            }

            $ch = null;
            
            preg_match('/<span id="appversion">(.*?)<\/span>/', $html, $matches);

            if (!empty($matches[1])) {
                $versaonova = $matches[1];

                if (version_compare($versaonova, $config->get('app', 'version'), '>')) {
                    $script->confirm($lang->get('%s update', $config->get('app', 'name')), $lang->get('The %s version is now available, do you want to download the new version?', $versaonova), 'question', function () use ($script) {
                        ob_start();
                        $script->noTag()->openURL($this->row('url'));
                        if ($this->row('show')) {
                            $script->noTag()->closeWindow();
                        }
                        $txt = ob_get_contents();
                        ob_end_clean();

                        return $txt;
                    }, function () use ($script) {
                        if ($this->row('show')) {
                            ob_start();
                            $script->noTag()->closeWindow();
                            $txt = ob_get_contents();
                            ob_end_clean();

                            return $txt;
                        }
                    });
                } else {
                    if ($this->row('show')) {
                        $script->alert($lang->get('%s update', $config->get('app', 'name')), $lang->get('The current version is the latest!'), 'info');
                        $script->closeWindow();
                    }
                }
            }
        } catch (\Exception $ex) {
            echo $lang->get('Error when searching for data: %s' . $ex->getMessage());
        }
    }
}
