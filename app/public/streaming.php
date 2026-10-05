<?php
$lang = htmlspecialchars($_ENV['MIPHANT_LANG'] ?? 'en');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Streaming | MiPhant</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>Streaming</h1>
    <p class="text-muted mb-3">Real-time response (fetch reader + Server-Sent Events)</p>
    <p class="text-muted">Platform: <code><?php echo htmlspecialchars($_ENV['MIPHANT_PLATFORM'] ?? 'unknown'); ?></code></p>

    <div class="card mb-3">
        <h3>fetch() com leitura incremental</h3>
        <p class="text-muted">Cada linha de <code>/stream.php</code> deve aparecer ~1s antes da proxima.</p>
        <button id="btn-fetch" class="btn">Run fetch stream</button>
        <pre id="out-fetch" class="text-muted mt-2"></pre>
    </div>

    <div class="card">
        <h3>EventSource (SSE)</h3>
        <p class="text-muted">10 eventos de <code>/sse.php</code>, um por segundo.</p>
        <button id="btn-sse" class="btn">Run SSE</button>
        <button id="btn-sse-stop" class="btn btn-outline" disabled>Stop</button>
        <pre id="out-sse" class="text-muted mt-2"></pre>
    </div>

    <a href="/" class="btn btn-outline mt-3">&larr; Back</a>

    <script>
        (function () {
            const outFetch = document.getElementById('out-fetch');
            const outSse = document.getElementById('out-sse');
            const btnFetch = document.getElementById('btn-fetch');
            const btnSse = document.getElementById('btn-sse');
            const btnSseStop = document.getElementById('btn-sse-stop');

            let eventSource = null;

            btnFetch.addEventListener('click', async function () {
                btnFetch.disabled = true;
                outFetch.textContent = '';

                try {
                    const response = await fetch('/stream.php');

                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;
                        outFetch.textContent += decoder.decode(value, { stream: true });
                    }
                } catch (error) {
                    outFetch.textContent += '\nERRO: ' + error.message;
                } finally {
                    btnFetch.disabled = false;
                }
            });

            btnSse.addEventListener('click', function () {
                outSse.textContent = '';
                btnSse.disabled = true;
                btnSseStop.disabled = false;

                eventSource = new EventSource('/sse.php');

                eventSource.onmessage = function (event) {
                    outSse.textContent += event.data + '\n';
                };

                eventSource.addEventListener('end', function () {
                    outSse.textContent += '[stream encerrado]\n';
                    closeSse();
                });

                eventSource.onerror = function () {
                    closeSse();
                };
            });

            btnSseStop.addEventListener('click', closeSse);

            function closeSse() {
                if (eventSource) {
                    eventSource.close();
                    eventSource = null;
                }
                btnSse.disabled = false;
                btnSseStop.disabled = true;
            }
        })();
    </script>
</body>
</html>
