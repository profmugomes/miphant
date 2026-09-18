<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
date_default_timezone_set('America/Sao_Paulo');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timezone | MiPhant</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>Timezone</h1>
    <p class="text-muted mb-3">Current date and time</p>

    <div class="card">
        <div class="stat">
            <div class="stat-value"><?php echo date('H:i:s'); ?></div>
            <div class="stat-label"><?php echo date('l, d F Y'); ?></div>
        </div>
        <p class="text-muted text-center mt-2">Timezone: <?php echo date('T'); ?> (UTC<?php echo date('P'); ?>)</p>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>
</body>
</html>
