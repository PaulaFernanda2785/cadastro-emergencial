<?php

$app = require BASE_PATH . '/config/app.php';
$pageTitle = $title ?? $app['name'];
$user = current_user();
$error = flash('error');
$success = flash('success');
$warning = flash('warning');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= h($pageTitle) ?> | <?= h($app['name']) ?></title>
    <link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
    <script src="<?= h(asset('js/forms.js')) ?>" defer></script>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="<?= h(url('/dashboard')) ?>">
            <span class="brand-mark">CE</span>
            <span><?= h($app['name']) ?></span>
        </a>

        <?php if ($user !== null): ?>
            <nav class="topbar-actions" aria-label="Navegacao principal">
                <a href="<?= h(url('/dashboard')) ?>">Painel</a>
                <?php if (($user['perfil'] ?? '') === 'administrador'): ?>
                    <a href="<?= h(url('/admin')) ?>">Admin</a>
                <?php endif; ?>
                <form method="post" action="<?= h(url('/logout')) ?>" class="inline-form js-prevent-double-submit">
                    <?= csrf_field() ?>
                    <button type="submit" data-loading-text="Saindo...">Sair</button>
                </form>
            </nav>
        <?php endif; ?>
    </header>

    <main class="main">
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" role="status"><?= h($success) ?></div>
        <?php endif; ?>

        <?php if ($warning): ?>
            <div class="alert alert-warning" role="alert"><?= h($warning) ?></div>
        <?php endif; ?>

        <?= $content ?>
    </main>
</body>
</html>
