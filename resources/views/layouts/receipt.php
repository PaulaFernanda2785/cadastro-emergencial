<?php

$app = require BASE_PATH . '/config/app.php';
$pageTitle = $title ?? 'Comprovante';
$success = flash('success');
$warning = flash('warning');
$error = flash('error');
$assetVersion = '20260502-129';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= h($pageTitle) ?> | <?= h($app['name']) ?></title>
    <link rel="stylesheet" href="<?= h(asset('css/app.css') . '?v=' . $assetVersion) ?>">
    <script src="<?= h(asset('js/qrcode.bundle.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/family-receipt.js') . '?v=' . $assetVersion) ?>" defer></script>
</head>
<body class="receipt-page">
    <main class="receipt-main">
        <?php if ($error): ?>
            <div class="alert alert-error no-print" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success no-print" role="status"><?= h($success) ?></div>
        <?php endif; ?>

        <?php if ($warning): ?>
            <div class="alert alert-warning no-print" role="alert"><?= h($warning) ?></div>
        <?php endif; ?>

        <?= $content ?>
    </main>
</body>
</html>
