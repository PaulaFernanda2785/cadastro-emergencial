<?php
$isOpen = $acao['status'] === 'aberta';
$cadastroUrl = '/acao/' . $acao['token_publico'] . '/residencias/novo';
$statusLabels = [
    'aberta' => 'Aberta',
    'encerrada' => 'Encerrada',
    'cancelada' => 'Cancelada',
];
?>

<section class="registration-app">
    <header class="registration-app-header">
        <span class="eyebrow">Aplicativo de cadastro</span>
        <h1><?= h($acao['localidade']) ?></h1>
        <p><?= h($acao['municipio_nome']) ?> / <?= h($acao['uf']) ?></p>
        <span class="status status-<?= h($acao['status']) ?>"><?= h($statusLabels[$acao['status']] ?? ucfirst((string) $acao['status'])) ?></span>
    </header>

    <section class="registration-app-summary" aria-label="Dados da ação">
        <div>
            <span>Evento</span>
            <strong><?= h($acao['tipo_evento']) ?></strong>
        </div>
        <div>
            <span>Data</span>
            <strong><?= h(date('d/m/Y', strtotime((string) $acao['data_evento']))) ?></strong>
        </div>
    </section>

    <?php if ($isOpen): ?>
        <section class="registration-app-panel">
            <h2>Cadastro de residência atingida</h2>
            <p>O cadastro será vinculado automaticamente a esta ação emergencial, mantendo município, localidade e evento no registro.</p>
            <a class="primary-link-button registration-app-button" href="<?= h(url($cadastroUrl)) ?>">
                <?= is_authenticated() ? 'Iniciar cadastro' : 'Entrar e iniciar cadastro' ?>
            </a>
            <small>O acesso exige usuário cadastrador, gestor ou administrador para manter a auditoria dos registros.</small>
        </section>
    <?php else: ?>
        <div class="alert alert-warning" role="alert">Esta ação não está aberta para novos cadastros.</div>
    <?php endif; ?>
</section>
