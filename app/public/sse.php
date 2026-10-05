<?php
// Server-Sent Events (SSE) em tempo real.
// O cliente usa EventSource e recebe um evento por segundo.

ob_implicit_flush(true);
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

set_time_limit(60);

for ($i = 1; $i <= 10; $i++) {
    $payload = json_encode([
        'sequence' => $i,
        'time' => date('H:i:s'),
        'message' => 'evento em tempo real'
    ], JSON_UNESCAPED_UNICODE);

    echo "data: {$payload}\n\n";
    flush();
    sleep(1);
}

echo "data: {\"sequence\": 0, \"message\": \"fim\"}\n\n";
echo "event: end\ndata: done\n\n";
flush();
