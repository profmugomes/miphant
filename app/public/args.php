<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arguments | MiPhant</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>Arguments</h1>
    <p class="text-muted mb-3">Command line arguments passed to MiPhant</p>

    <div class="card">
        <h3>How to use</h3>
        <pre><code>Linux:   ./miphant arg1 arg2 arg3
Windows: miphant.exe arg1 arg2 arg3</code></pre>
    </div>

    <div class="card">
        <h3>Arguments received</h3>
        <?php
        $argv = $_ENV['MIPHANT_ARGV'] ?? '';
        if (empty($argv)) {
            echo '<p class="text-muted">No arguments found.</p>';
        } else {
            echo '<p><strong>' . htmlspecialchars($argv) . '</strong></p>';
        }
        ?>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>
</body>
</html>
