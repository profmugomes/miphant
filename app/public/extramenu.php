<?php
require_once(__DIR__ . '/vendor/autoload.php');

use MiPhantLibs\app\functions;

$func = new functions();
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extra Menu | MiPhant</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>Extra Menu</h1>
    <p class="text-muted mb-3">Custom menus per window</p>

    <div class="card">
        <h2>Demo</h2>
        <p class="text-muted">Open this page in a new window to see the custom menu in action.</p>
        <button class="btn btn-primary" onclick="openNew()">Open in New Window</button>
    </div>

    <div class="card">
        <h2>How to create custom menus</h2>
        <p>Create a JSON file in <code>app/menus/</code> with the same name as your PHP page.</p>
        <p>Example: for <code>mypage.php</code>, create <code>mypage.json</code>.</p>

        <h3 class="mt-2">Menu format</h3>
        <pre><code>{
    "Menu Name": {
        "Item Label": {
            "page": "/target",
            "key": "Ctrl+O",
            "newwindow": true
        }
    }
}</code></pre>

        <h3 class="mt-2">Properties</h3>
        <table>
            <tr><th>Property</th><th>Type</th><th>Description</th></tr>
            <tr><td><code>page</code></td><td>string</td><td>Page to navigate to</td></tr>
            <tr><td><code>url</code></td><td>string</td><td>External URL (opens in system browser)</td></tr>
            <tr><td><code>script</code></td><td>string</td><td>JavaScript to execute</td></tr>
            <tr><td><code>key</code></td><td>string</td><td>Keyboard shortcut</td></tr>
            <tr><td><code>newwindow</code></td><td>boolean</td><td>Open in new window</td></tr>
        </table>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>

    <script>
        function openNew() {
            <?php $func->noTag()->newWindow('/extramenu', 900, 600, true, true, false, 'extramenu'); ?>
        }
    </script>
</body>
</html>
