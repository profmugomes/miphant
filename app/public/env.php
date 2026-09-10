<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Environment | MiPhant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Environment</h1>
    <p class="text-muted mb-3">MiPhant environment variables</p>

    <div class="card">
        <table>
            <tr>
                <th>Variable</th>
                <th>Value</th>
            </tr>
            <tr>
                <td><strong>Username</strong></td>
                <td><?php echo htmlspecialchars($_ENV['MIPHANT_USERNAME'] ?? ''); ?></td>
            </tr>
            <tr>
                <td><strong>Home Directory</strong></td>
                <td><?php echo htmlspecialchars($_ENV['MIPHANT_HOMEDIR'] ?? ''); ?></td>
            </tr>
            <tr>
                <td><strong>Platform</strong></td>
                <td>
                    <span class="badge badge-info"><?php echo htmlspecialchars($_ENV['MIPHANT_PLATFORM'] ?? ''); ?></span>
                </td>
            </tr>
            <tr>
                <td><strong>Language</strong></td>
                <td><?php echo htmlspecialchars($_ENV['MIPHANT_LANG'] ?? ''); ?></td>
            </tr>
            <tr>
                <td><strong>App Path</strong></td>
                <td><?php echo htmlspecialchars(__DIR__); ?></td>
            </tr>
        </table>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>
</body>
</html>
