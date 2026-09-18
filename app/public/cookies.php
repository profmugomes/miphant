<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';

$count = empty($_COOKIE['info']) ? 1 : ($_COOKIE['info']['msg'] ?? 0) + 1;
setcookie('info[msg]', $count, 0, '/', '', false, true);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cookies | MiPhant</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>Cookies</h1>
    <p class="text-muted mb-3">HTTP cookies example</p>

    <div class="card">
        <div class="stat mb-2">
            <div class="stat-value" id="counter"><?php echo $_COOKIE['info']['msg'] ?? 0; ?></div>
            <div class="stat-label">Cookie Counter</div>
        </div>
        <button class="btn btn-primary" onclick="location.reload()">Increment</button>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>


</body>
</html>
