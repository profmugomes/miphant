<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MiPhant</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>MiPhant</h1>
    <p class="text-muted mb-3">Run and develop PHP applications for desktop</p>

    <div class="grid grid-4 mb-3" id="versions">
        <div class="stat">
            <div class="stat-value" id="v-miphant">...</div>
            <div class="stat-label">MiPhant</div>
        </div>
        <div class="stat">
            <div class="stat-value" id="v-electron">...</div>
            <div class="stat-label">Electron</div>
        </div>
        <div class="stat">
            <div class="stat-value" id="v-node">...</div>
            <div class="stat-label">Node.js</div>
        </div>
        <div class="stat">
            <div class="stat-value" id="v-chromium">...</div>
            <div class="stat-label">Chromium</div>
        </div>
    </div>

    <div class="card">
        <h2>Examples</h2>
        <ul>
            <?php
            $files = scandir(__DIR__);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === 'index.php' || $file === 'style.css' || is_dir(__DIR__ . '/' . $file)) {
                    continue;
                }
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php' && basename($file) !== 'index.php') {
                    $name = ucfirst(str_replace('.php', '', $file));
                    printf('<li><a href="%s">%s</a></li>', $file, $name);
                }
            }
            ?>
        </ul>
    </div>

    <div class="card text-muted">
        <p>PHP <?php echo phpversion(); ?> | <?php echo $_ENV['MIPHANT_PLATFORM'] ?? ''; ?> | <?php echo $_ENV['MIPHANT_USERNAME'] ?? ''; ?></p>
    </div>

    <script>
        async function loadVersions() {
            const fields = {
                'v-miphant': 'miphant',
                'v-electron': 'electron',
                'v-node': 'node',
                'v-chromium': 'chromium'
            };

            for (const [id, type] of Object.entries(fields)) {
                const el = document.getElementById(id);
                if (el) el.textContent = await miphant.version(type);
            }
        }

        loadVersions();

        miphant.tray('MiPhant', 'MiPhant', '', JSON.stringify({
            "Home": { page: "index.php" },
            "Message": { script: "miphant.alert('MiPhant', 'Hello from tray!', 'info', 'OK');" },
            "Close": { script: "miphant.close();" }
        }));
    </script>
</body>
</html>
