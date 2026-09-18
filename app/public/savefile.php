<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';

if (!empty($_GET['filename'])) {
    $filename = filter_input(INPUT_GET, 'filename', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($filename) {
        file_put_contents($filename, 'Hello from MiPhant! ' . date('Y-m-d H:i:s'));
        echo '<!DOCTYPE html><html><head><title>Saved</title>';
        echo '<link rel="stylesheet" href="/style.css">';
        echo '</head><body>';
        echo '<h1>File Saved</h1>';
        echo '<div class="card"><p class="text-success">File <strong>' . htmlspecialchars(basename($filename)) . '</strong> saved successfully!</p></div>';
        echo '<a href="savefile.php" class="btn btn-outline">&larr; Save another</a>';
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
    <title>Save File | MiPhant</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>Save File</h1>
    <p class="text-muted mb-3">Save content to a file on the system</p>

    <div class="card">
        <button class="btn btn-success" onclick="saveFile()">Select Location &amp; Save</button>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>

    <script>
        async function saveFile() {
            const file = await miphant.saveFile();
            if (file) {
                window.location.assign(`?filename=${encodeURIComponent(file)}`);
            }
        }
    </script>
</body>
</html>
