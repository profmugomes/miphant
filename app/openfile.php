<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';

if (!empty($_GET['filename'])) {
    $filename = filter_input(INPUT_GET, 'filename', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($filename && file_exists($filename)) {
        echo '<!DOCTYPE html><html><head><title>File Content</title>';
        echo '<link rel="stylesheet" href="style.css">';
        echo '</head><body>';
        echo '<h1>' . htmlspecialchars(basename($filename)) . '</h1>';
        echo '<div class="card"><pre><code>' . htmlspecialchars(file_get_contents($filename)) . '</code></pre></div>';
        echo '<a href="openfile.php" class="btn btn-outline">&larr; Open another file</a>';
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
    <title>Open File | MiPhant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Open File</h1>
    <p class="text-muted mb-3">Open and read a file from the system</p>

    <div class="card">
        <button class="btn btn-primary" onclick="openFile()">Select File</button>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>

    <script>
        async function openFile() {
            const file = await miphant.openFile();
            if (file) {
                window.location.assign(`?filename=${encodeURIComponent(file)}`);
            }
        }
    </script>
</body>
</html>
