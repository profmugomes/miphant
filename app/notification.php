<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification | MiPhant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Notification</h1>
    <p class="text-muted mb-3">System notification</p>

    <div class="card">
        <h2>Send Notification</h2>
        <p class="text-muted">Display a desktop notification</p>
        <button class="btn btn-primary" onclick="sendNotification()">Send Notification</button>
    </div>

    <div id="result" class="card hidden">
        <h3>Result</h3>
        <p class="text-success">Notification sent!</p>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>

    <script>
        function sendNotification() {
            miphant.notification('MiPhant', 'This is a desktop notification!');
            document.getElementById('result').classList.remove('hidden');
        }
    </script>
</body>
</html>
