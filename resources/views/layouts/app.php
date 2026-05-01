<?php

$app = require BASE_PATH . '/config/app.php';
$pageTitle = $title ?? $app['name'];
$user = current_user();
$error = flash('error');
$success = flash('success');
$warning = flash('warning');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$profileLabel = [
    'administrador' => 'Administrador',
    'gestor' => 'Gestor',
    'cadastrador' => 'Cadastrador',
][$user['perfil'] ?? ''] ?? 'Visitante';
$activeActionToken = App\Core\Session::get('active_action_token');
$activeActionUrl = is_string($activeActionToken) && $activeActionToken !== ''
    ? '/acao/' . rawurlencode($activeActionToken) . '/residencias/novo'
    : null;
$assetVersion = '20260501-35';

$menuItems = [
    ['group' => 'Operacao', 'label' => 'Painel situacional', 'abbr' => 'PS', 'url' => '/dashboard', 'match' => ['/dashboard', '/']],
    ['group' => 'Operacao', 'label' => 'Novo cadastro', 'abbr' => 'NC', 'url' => $activeActionUrl, 'match' => ['/acao/'], 'roles' => ['cadastrador', 'gestor', 'administrador'], 'requires_action' => true],
    ['group' => 'Operacao', 'label' => 'Cadastros', 'abbr' => 'CD', 'url' => '/cadastros/residencias', 'match' => ['/cadastros/residencias']],
    ['group' => 'Operacao', 'label' => 'Familias', 'abbr' => 'FM', 'url' => '/gestor/familias', 'match' => ['/gestor/familias']],
    ['group' => 'Gestao', 'label' => 'Entregas', 'abbr' => 'EN', 'url' => '/gestor/entregas', 'match' => ['/gestor/entregas'], 'roles' => ['gestor', 'administrador']],
    ['group' => 'Gestao', 'label' => 'Prestacao de contas', 'abbr' => 'PC', 'url' => '/gestor/prestacao-contas', 'match' => ['/gestor/prestacao-contas'], 'roles' => ['gestor', 'administrador']],
    ['group' => 'Gestao', 'label' => 'Relatorios', 'abbr' => 'RL', 'url' => '/gestor/relatorios', 'match' => ['/gestor/relatorios'], 'roles' => ['gestor', 'administrador']],
    ['group' => 'Administracao', 'label' => 'Acoes emergenciais', 'abbr' => 'AE', 'url' => '/admin/acoes', 'match' => ['/admin/acoes'], 'roles' => ['administrador']],
    ['group' => 'Administracao', 'label' => 'Tipos de ajuda', 'abbr' => 'TA', 'url' => '/admin/ajudas', 'match' => ['/admin/ajudas'], 'roles' => ['administrador']],
    ['group' => 'Administracao', 'label' => 'Usuarios', 'abbr' => 'US', 'url' => '/admin/usuarios', 'match' => ['/admin/usuarios'], 'roles' => ['administrador']],
    ['group' => 'Conta', 'label' => 'Alterar senha', 'abbr' => 'AS', 'url' => '/alterar-senha', 'match' => ['/alterar-senha']],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#155e75">
    <title><?= h($pageTitle) ?> | <?= h($app['name']) ?></title>
    <script>
        (function () {
            try {
                var stored = window.localStorage.getItem('cadastroEmergencial.sidebarCollapsed');
                var small = window.matchMedia('(max-width: 760px)').matches;
                if (stored === 'true' || (stored === null && small)) {
                    document.documentElement.classList.add('sidebar-collapsed-initial');
                }
            } catch (error) {
            }
        })();
    </script>
    <link rel="manifest" href="<?= h(url('/manifest.webmanifest') . '?v=' . $assetVersion) ?>">
    <link rel="apple-touch-icon" href="<?= h(asset('images/logo-cadastro-emergencial-app.png')) ?>">
    <link rel="stylesheet" href="<?= h(asset('css/app.css') . '?v=' . $assetVersion) ?>">
    <script src="<?= h(asset('js/modals.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/forms.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/layout.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/app-offline.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/geolocation.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/action-form.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/residence-form.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/residence-detail.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/family-form.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/offline-queue.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/qrcode.bundle.js') . '?v=' . $assetVersion) ?>" defer></script>
    <script src="<?= h(asset('js/actions-index.js') . '?v=' . $assetVersion) ?>" defer></script>
</head>
<body>
    <div class="app-shell" data-layout-shell>
        <?php if ($user !== null): ?>
            <aside class="sidebar" data-sidebar>
                <div class="sidebar-brand">
                    <a class="brand" href="<?= h(url('/dashboard')) ?>" aria-label="<?= h($app['name']) ?>">
                        <img src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA" class="brand-logo">
                        <span class="brand-text">
                            <strong><?= h($app['name']) ?></strong>
                            <small>CEDEC-PA</small>
                        </span>
                    </a>
                </div>

                <nav class="sidebar-nav" aria-label="Menu principal">
                    <?php $currentGroup = null; ?>
                    <?php foreach ($menuItems as $item): ?>
                        <?php
                        $roles = $item['roles'] ?? null;
                        if (is_array($roles) && !in_array((string) ($user['perfil'] ?? ''), $roles, true)) {
                            continue;
                        }

                        if (!empty($item['requires_action']) && $activeActionUrl === null) {
                            continue;
                        }

                        if (($item['group'] ?? '') !== $currentGroup) {
                            $currentGroup = $item['group'] ?? '';
                            echo '<span class="sidebar-section-title">' . h($currentGroup) . '</span>';
                        }

                        $isActive = false;
                        foreach ($item['match'] as $match) {
                            if ($match === '/' ? $currentPath === '/' : str_starts_with($currentPath, $match)) {
                                $isActive = true;
                                break;
                            }
                        }
                        ?>
                        <a class="sidebar-link <?= $isActive ? 'is-active' : '' ?>" href="<?= h(url((string) $item['url'])) ?>" title="<?= h($item['label']) ?>">
                            <span class="nav-initial"><?= h($item['abbr']) ?></span>
                            <span class="nav-label"><?= h($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>
        <?php endif; ?>

        <div class="app-content">
            <header class="topbar">
                <div class="topbar-left">
                    <?php if ($user !== null): ?>
                        <button class="menu-toggle" type="button" data-sidebar-toggle aria-label="Recolher menu" aria-expanded="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>
                    <?php endif; ?>
                    <div class="institution-title">
                        <strong>Corpo de Bombeiros Militar do Para</strong>
                        <span>Coordenadoria Estadual de Protecao e Defesa Civil</span>
                    </div>
                </div>

                <?php if ($user !== null): ?>
                    <div class="user-area">
                        <div class="user-summary">
                            <strong><?= h($user['nome'] ?? 'Usuario') ?></strong>
                            <span><?= h($profileLabel) ?></span>
                        </div>
                        <form method="post" action="<?= h(url('/logout')) ?>" class="inline-form js-prevent-double-submit">
                            <?= csrf_field() ?>
                            <button type="submit" class="logout-button" data-loading-text="Saindo...">Sair</button>
                        </form>
                    </div>
                <?php else: ?>
                    <a class="public-brand" href="<?= h(url('/login')) ?>"><?= h($app['name']) ?></a>
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

            <footer class="app-footer">
                <span><?= h($app['name']) ?> - versao 1.0.0 producao</span>
                <span>2026 todos os direitos reservados.</span>
                <span>Desenvolvido pela Divisao de Gestao de Risco - DGR/CEDEC-PA</span>
            </footer>
        </div>
    </div>
</body>
</html>
