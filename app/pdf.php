<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
$pdfDir = __DIR__ . '/pdf';
if (!file_exists($pdfDir)) {
    mkdir($pdfDir, 0755, true);
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF | MiPhant</title>
    <link rel="stylesheet" href="style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; color: black; padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <h1>PDF</h1>
        <p class="text-muted mb-3">Export page content to PDF</p>

        <div class="card">
            <button class="btn btn-primary" onclick="exportPDF()">Export PDF</button>
        </div>
    </div>

    <div class="card" style="margin-top: 1rem;">
        <h2>Preview Content</h2>
        <table>
            <tr>
                <td><strong>Date/Time:</strong></td>
                <td><?php echo date('Y-m-d H:i:s'); ?></td>
            </tr>
            <tr>
                <td><strong>Platform:</strong></td>
                <td><?php echo $_ENV['MIPHANT_PLATFORM'] ?? ''; ?></td>
            </tr>
            <tr>
                <td><strong>User:</strong></td>
                <td><?php echo $_ENV['MIPHANT_USERNAME'] ?? ''; ?></td>
            </tr>
        </table>
    </div>

    <div class="no-print">
        <a href="index.php" class="btn btn-outline mt-2">&larr; Back</a>
    </div>

    <script>
        async function exportPDF() {
            <?php if (($_ENV['MIPHANT_PLATFORM'] ?? '') === 'linux'): ?>
                const filename = '<?php echo $pdfDir; ?>/example.pdf';
            <?php else: ?>
                const filename = '<?php echo str_replace('\\', '\\\\', $pdfDir); ?>\\\\example.pdf';
            <?php endif; ?>

            await miphant.exportPDF(filename);

            while (!(await miphant.fileExists(filename))) {
                await new Promise(r => setTimeout(r, 200));
            }

            miphant.newWindow('pdf/example.pdf');
        }
    </script>
</body>
</html>
