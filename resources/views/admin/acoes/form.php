<section class="form-shell">
    <div class="section-heading">
        <span class="eyebrow">Administracao</span>
        <h1><?= h($title) ?></h1>
        <p>O token publico sera usado no link/QR Code da acao. Ele nao deve conter dados pessoais.</p>
    </div>

    <form method="post" action="<?= h(url($action)) ?>" class="form panel-form js-prevent-double-submit" novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <label class="field">
            <span>Municipio</span>
            <select name="municipio_id" required>
                <option value="">Selecione</option>
                <?php foreach ($municipios as $municipio): ?>
                    <option value="<?= h($municipio['id']) ?>" <?= (string) ($acao['municipio_id'] ?? '') === (string) $municipio['id'] ? 'selected' : '' ?>>
                        <?= h($municipio['nome']) ?> / <?= h($municipio['uf']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['municipio_id'])): ?>
                <small class="field-error"><?= h($errors['municipio_id'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Localidade, bairro ou comunidade</span>
            <input type="text" name="localidade" value="<?= h($acao['localidade'] ?? '') ?>" maxlength="180" required>
            <?php if (!empty($errors['localidade'])): ?>
                <small class="field-error"><?= h($errors['localidade'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Tipo de evento</span>
            <input type="text" name="tipo_evento" value="<?= h($acao['tipo_evento'] ?? '') ?>" maxlength="180" placeholder="Ex.: Enxurradas, Inundacoes, Vendaval" required>
            <?php if (!empty($errors['tipo_evento'])): ?>
                <small class="field-error"><?= h($errors['tipo_evento'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Data do evento</span>
            <input type="date" name="data_evento" value="<?= h($acao['data_evento'] ?? date('Y-m-d')) ?>" required>
            <?php if (!empty($errors['data_evento'])): ?>
                <small class="field-error"><?= h($errors['data_evento'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Status</span>
            <select name="status">
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= h($status) ?>" <?= ($acao['status'] ?? 'aberta') === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['status'])): ?>
                <small class="field-error"><?= h($errors['status'][0]) ?></small>
            <?php endif; ?>
        </label>

        <?php if (!empty($acao['token_publico'])): ?>
            <div class="readonly-field">
                <span>Link publico</span>
                <a href="<?= h(url('/acao/' . $acao['token_publico'])) ?>" target="_blank" rel="noopener"><?= h(url('/acao/' . $acao['token_publico'])) ?></a>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Processando...">
                <span class="button-label">Salvar</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/admin/acoes')) ?>">Cancelar</a>
        </div>
    </form>
</section>
