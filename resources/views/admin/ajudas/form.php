<section class="form-shell">
    <div class="section-heading">
        <span class="eyebrow">Administracao</span>
        <h1><?= h($title) ?></h1>
        <p>Use nomes objetivos, pois eles aparecem em entregas, comprovantes e prestacao de contas.</p>
    </div>

    <form method="post" action="<?= h(url($action)) ?>" class="form panel-form js-prevent-double-submit" novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <label class="field">
            <span>Nome do material</span>
            <input type="text" name="nome" value="<?= h($tipo['nome'] ?? '') ?>" maxlength="180" required>
            <?php if (!empty($errors['nome'])): ?>
                <small class="field-error"><?= h($errors['nome'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Unidade de medida</span>
            <input type="text" name="unidade_medida" value="<?= h($tipo['unidade_medida'] ?? '') ?>" maxlength="50" placeholder="kit, cesta, unidade, pacote" required>
            <?php if (!empty($errors['unidade_medida'])): ?>
                <small class="field-error"><?= h($errors['unidade_medida'][0]) ?></small>
            <?php endif; ?>
        </label>

        <?php if (!empty($tipo['id'])): ?>
            <label class="field">
                <span>Status</span>
                <select name="ativo">
                    <option value="1" <?= (int) ($tipo['ativo'] ?? 1) === 1 ? 'selected' : '' ?>>Ativo</option>
                    <option value="0" <?= (int) ($tipo['ativo'] ?? 1) === 0 ? 'selected' : '' ?>>Inativo</option>
                </select>
            </label>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Processando...">
                <span class="button-label">Salvar</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/admin/ajudas')) ?>">Cancelar</a>
        </div>
    </form>
</section>
