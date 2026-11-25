<?php
// Pagina de diagnostico: expoe o ambiente do PHP.
// Disponivel apenas com MIPHANT_DEV=1 (config.dev.tools), nunca em producao.
if (($_ENV['MIPHANT_DEV'] ?? '0') !== '1') {
    http_response_code(404);
    exit;
}

phpinfo();
