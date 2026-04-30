<section class="public-action">
    <div class="section-heading">
        <span class="eyebrow">Acao emergencial</span>
        <h1><?= h($acao['localidade']) ?></h1>
        <p><?= h($acao['municipio_nome']) ?> / <?= h($acao['uf']) ?> - <?= h($acao['tipo_evento']) ?></p>
    </div>

    <div class="action-summary">
        <div>
            <span>Data do evento</span>
            <strong><?= h(date('d/m/Y', strtotime((string) $acao['data_evento']))) ?></strong>
        </div>
        <div>
            <span>Status</span>
            <strong><?= h(ucfirst($acao['status'])) ?></strong>
        </div>
    </div>

    <?php if ($acao['status'] === 'aberta'): ?>
        <div class="module-list">
            <h2>Cadastro de campo</h2>
            <p>Esta acao esta habilitada para cadastro de residencias e familias atingidas.</p>
            <?php if (is_authenticated()): ?>
                <a class="primary-link-button" href="<?= h(url('/acao/' . $acao['token_publico'] . '/residencias/novo')) ?>">Cadastrar residencia</a>
            <?php else: ?>
                <a class="primary-link-button" href="<?= h(url('/login')) ?>">Entrar para cadastrar</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning" role="alert">Esta acao nao esta aberta para novos cadastros.</div>
    <?php endif; ?>
</section>
