<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
session_name('miphant');
session_start();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session | MiPhant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Session</h1>
    <p class="text-muted mb-3">PHP session example</p>

    <div class="card">
        <?php
        $count = ($_SESSION['counter'] ?? 0) + 1;
        $_SESSION['counter'] = $count;
        ?>
        <div class="stat mb-2">
            <div class="stat-value"><?php echo $count; ?></div>
            <div class="stat-label">Session Counter</div>
        </div>
        <button class="btn btn-primary" onclick="location.reload()">Increment</button>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>
</body>
</html>
