<?php
// Demo: streaming de resposta em tempo real.
// Cada linha deve aparecer ~1s antes da proxima (nao tudo no fim).

// Garante que a resposta saia do PHP imediatamente
ob_implicit_flush(true);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // evita buffering em proxies (se houver)

// Evita que o max_execution_time mate o stream cedo
set_time_limit(60);

for ($i = 1; $i <= 10; $i++) {
    echo sprintf("[%s] linha %d de 10\n", date('H:i:s'), $i);
    flush();
    sleep(1);
}

echo sprintf("[%s] fim do stream\n", date('H:i:s'));
