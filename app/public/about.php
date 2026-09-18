<?php
require_once(__DIR__ . '/vendor/autoload.php');

use MiPhantLibs\app\about;
use MiPhantLibs\langs\translate;

$about = new about();
$translate = new translate();
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
$license = file_get_contents(__DIR__ . '/../../LICENSE.md');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About | MiPhant</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>About MiPhant</h1>
    <p class="text-muted mb-3">Desktop PHP applications with Electron</p>

    <div class="card text-center">
        <h2>MiPhant</h2>
        <div class="stat-value mt-1" id="version">...</div>
        <p class="text-muted mt-1">Run PHP applications on your desktop</p>
        <p class="mt-2">
            <a href="https://github.com/profmugomes/miphant" class="btn btn-primary" target="_blank">GitHub</a>
        </p>
    </div>

    <div class="card">
        <h2>System Information</h2>
        <table>
            <tr><td><strong>Platform</strong></td><td><span class="badge badge-info"><?php echo PHP_OS_FAMILY; ?></span></td></tr>
            <tr><td><strong>PHP Version</strong></td><td><?php echo phpversion(); ?></td></tr>
            <tr><td><strong>Username</strong></td><td><?php echo htmlspecialchars($_ENV['MIPHANT_USERNAME'] ?? ''); ?></td></tr>
            <tr><td><strong>Language</strong></td><td><?php echo htmlspecialchars($lang); ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h2>License</h2>
        <?php echo $about->setLicense('PolyForm Perimeter License', $license); ?>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>

    <script>
        async function loadVersion() {
            const el = document.getElementById('version');
            el.textContent = 'v' + await miphant.version('miphant');
        }
        loadVersion();

        var coll = document.getElementsByClassName('collapsible');
        for (var i = 0; i < coll.length; i++) {
            coll[i].addEventListener('click', function() {
                this.classList.toggle('active');
                var content = this.nextElementSibling;
                content.style.display = content.style.display === 'block' ? 'none' : 'block';
            });
        }
    </script>
</body>
</html>
