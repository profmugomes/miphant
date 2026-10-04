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
            <li><a href="/about" target="_blank" rel="noopener">About</a></li>
            <li><a href="/args" target="_blank" rel="noopener">Args</a></li>
            <li><a href="/cookies" target="_blank" rel="noopener">Cookies</a></li>
            <li><a href="/extramenu" target="_blank" rel="noopener">Extra Menu</a></li>
            <li><a href="/formget" target="_blank" rel="noopener">Form GET</a></li>
            <li><a href="/formpost" target="_blank" rel="noopener">Form POST</a></li>
            <li><a href="/libs" target="_blank" rel="noopener">Libs</a></li>
            <li><a href="/message" target="_blank" rel="noopener">Message</a></li>
            <li><a href="/notification" target="_blank" rel="noopener">Notification</a></li>
            <li><a href="/env" target="_blank" rel="noopener">Env</a></li>
            <li><a href="/openfile" target="_blank" rel="noopener">Open File</a></li>
            <li><a href="/openfiles" target="_blank" rel="noopener">Open Files</a></li>
            <li><a href="/phpinfo" target="_blank" rel="noopener">PHP Info</a></li>
            <li><a href="/preload-doc" target="_blank" rel="noopener">Preload Doc</a></li>
            <li><a href="/savefile" target="_blank" rel="noopener">Save File</a></li>
            <li><a href="/selectdirectory" target="_blank" rel="noopener">Select Directory</a></li>
            <li><a href="/pdf" target="_blank" rel="noopener">PDF</a></li>
            <li><a href="/session" target="_blank" rel="noopener">Session</a></li>
            <li><a href="/cadastro/listedit" target="_blank" rel="noopener">List Edit</a></li>
            <li><a href="/cadastro/listedit/1/" target="_blank" rel="noopener">List Edit with ID</a></li>
            <li><a href="/timezone" target="_blank" rel="noopener">Timezone</a></li>
            <li><a href="/translate" target="_blank" rel="noopener">Traduzir</a></li>
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
