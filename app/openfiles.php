<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Files | MiPhant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Open Files</h1>
    <p class="text-muted mb-3">Open and list multiple files</p>

    <div class="card">
        <button class="btn btn-primary" onclick="openFiles()">Select Files</button>
    </div>

    <div class="card hidden" id="result">
        <h3>Selected Files</h3>
        <ul id="file-list"></ul>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>

    <script>
        async function openFiles() {
            const files = await miphant.openFile(true);
            if (files && files.length > 0) {
                const list = document.getElementById('file-list');
                const card = document.getElementById('result');
                list.innerHTML = '';
                files.forEach(f => {
                    list.innerHTML += `<li>${f}</li>`;
                });
                card.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>
