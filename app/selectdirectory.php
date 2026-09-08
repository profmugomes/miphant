<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';

if (!empty($_GET['directory'])) {
    $directory = filter_input(INPUT_GET, 'directory', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($directory) {
        echo '<!DOCTYPE html><html><head><title>Directory</title>';
        echo '<link rel="stylesheet" href="style.css">';
        echo '</head><body>';
        echo '<h1>Selected Directory</h1>';
        echo '<div class="card"><p><strong>' . htmlspecialchars($directory) . '</strong></p></div>';
        echo '<a href="selectdirectory.php" class="btn btn-outline">&larr; Select another</a>';
        echo '</body></html>';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Directory | MiPhant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Select Directory</h1>
    <p class="text-muted mb-3">Choose a directory from the system</p>

    <div class="card">
        <button class="btn btn-primary" onclick="selectDir()">Select Directory</button>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>

    <script>
        async function selectDir() {
            const dir = await miphant.selectDirectory();
            if (dir) {
                window.location.assign(`?directory=${encodeURIComponent(dir)}`);
            }
        }
    </script>
</body>
</html>
