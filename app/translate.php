<?php
require_once __DIR__ . '/libs/app/config.php';
require_once __DIR__ . '/libs/app/functions.php';
require_once __DIR__ . '/libs/app/path.php';
require_once __DIR__ . '/libs/langs/translate.php';
require_once __DIR__ . '/libs/system/server.php';
require_once __DIR__ . '/libs/system/env.php';
require_once __DIR__ . '/libs/system/platform.php';

use MiPhantLibs\langs\translate;
use MiPhantLibs\app\functions;

$translate = new translate();
$func = new functions();
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Translate | MiPhant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Translate</h1>
    <p class="text-muted mb-3">i18n examples in PHP and JavaScript</p>

    <div class="card">
        <h2>PHP (via MiPhantLibs)</h2>
        <table>
            <tr>
                <th>Key</th>
                <th>Translated</th>
            </tr>
            <tr>
                <td><code>Continue</code></td>
                <td><?php echo $translate->get('Continue'); ?></td>
            </tr>
            <tr>
                <td><code>Cancel</code></td>
                <td><?php echo $translate->get('Cancel'); ?></td>
            </tr>
            <tr>
                <td><code>Unable to find file %s</code></td>
                <td><?php echo $translate->get('Unable to find file %s', 'example.txt'); ?></td>
            </tr>
            <tr>
                <td><code>Error starting the server:</code></td>
                <td><?php echo $translate->get('Error starting the server:'); ?></td>
            </tr>
            <tr>
                <td><code>Server has been started successfully.</code></td>
                <td><?php echo $translate->get('Server has been started successfully.'); ?></td>
            </tr>
        </table>
    </div>

    <div class="card">
        <h2>JavaScript (via preload)</h2>
        <div id="js-translations">Loading...</div>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>

    <script>
        async function loadTranslations() {
            const el = document.getElementById('js-translations');
            const keys = [
                'Continue',
                'Cancel',
                'Unable to find file %s',
                'Error starting the server:',
                'Server has been started successfully.'
            ];
            let html = '<table><tr><th>Key</th><th>Translated</th></tr>';
            for (const key of keys) {
                const t = key.includes('%s')
                    ? await miphant.translate(key, 'test.js')
                    : await miphant.translate(key);
                html += `<tr><td><code>${key}</code></td><td>${t}</td></tr>`;
            }
            html += '</table>';
            el.innerHTML = html;
        }
        loadTranslations();
    </script>
</body>
</html>
