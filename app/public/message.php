<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message | MiPhant</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>Message</h1>
    <p class="text-muted mb-3">Alert and confirm dialogs</p>

    <div class="card">
        <h2>Alert</h2>
        <p class="text-muted">Display a simple alert message</p>
        <button class="btn btn-primary" onclick="showAlert()">Show Alert</button>
    </div>

    <div class="card">
        <h2>Confirm</h2>
        <p class="text-muted">Display a confirmation dialog with multiple buttons</p>
        <button class="btn btn-primary" onclick="showConfirm()">Show Confirm</button>
    </div>

    <div class="card" id="result" class="hidden">
        <h3>Result</h3>
        <p id="result-text"></p>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>

    <script>
        function showAlert() {
            miphant.alert('Information', 'This is an example alert message!', 'info', 'Continue');
        }

        function showConfirm() {
            miphant.confirm(
                'Confirmation',
                'Do you want to continue?',
                'question',
                'Yes',
                'No'
            ).then((result) => {
                const el = document.getElementById('result');
                const text = document.getElementById('result-text');
                el.classList.remove('hidden');

                if (result === 0) {
                    text.innerHTML = '<span class="text-success">Confirmed (Yes)</span>';
                } else {
                    text.innerHTML = '<span class="text-danger">Cancelled (No)</span>';
                }
            });
        }
    </script>
</body>
</html>
