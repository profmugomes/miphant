<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form GET | MiPhant</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>Form GET</h1>
    <p class="text-muted mb-3">HTML form with GET method</p>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['name'])): ?>
    <div class="card">
        <h3>Result</h3>
        <p>Hello, <strong><?php echo htmlspecialchars(filter_input(INPUT_GET, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS)); ?></strong>!</p>
    </div>
    <?php endif; ?>

    <div class="card">
        <form method="get" action="/formget">
            <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" id="name" name="name" placeholder="Type your name..." required>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>
</body>
</html>
