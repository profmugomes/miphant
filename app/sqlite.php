<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';

$dbPath = __DIR__ . '/dados/example.sqlite';
$db = new SQLite3($dbPath);
$db->exec("CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now'))
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ($msg) {
        $stmt = $db->prepare("INSERT INTO logs (message) VALUES (:msg)");
        $stmt->bindValue(':msg', $msg, SQLITE3_TEXT);
        $stmt->execute();
        header('Location: sqlite.php');
        exit;
    }
}

$result = $db->query('SELECT * FROM logs ORDER BY id DESC LIMIT 20');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQLite3 | MiPhant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>SQLite3</h1>
    <p class="text-muted mb-3">SQLite database example</p>



    <div class="card">
        <h2>Add Record</h2>
        <form method="post">
            <div class="form-group">
                <label for="message">Message</label>
                <input type="text" id="message" name="message" placeholder="Type a message..." required>
            </div>
            <button type="submit" class="btn btn-success">Insert</button>
        </form>
    </div>

    <div class="card">
        <h2>Records</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Message</th>
                <th>Created</th>
            </tr>
            <?php while ($row = $result->fetchArray(SQLITE3_ASSOC)): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['message']); ?></td>
                <td><?php echo $row['created_at']; ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <?php $db->close(); ?>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>
</body>
</html>
