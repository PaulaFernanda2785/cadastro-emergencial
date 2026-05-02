<?php
$isEditing = !empty($tipo['id']);
$ativo = (int) ($tipo['ativo'] ?? 1) === 1;
?>

<section class="action-form-page aid-type-form-page">
    <header class="action-form-header">
        <div>
            <span class="eyebrow">Administracao</span>
            <h1><?= h($title) ?></h1>
            <p>Use nomes objetivos, pois eles aparecem nas entregas, comprovantes, historico e prestacao de contas.</p>
        </div>
        <a class="secondary-button action-back-link" href="<?= h(url('/admin/ajudas')) ?>">Voltar para tipos</a>
    </header>

    <form method="post" action="<?= h(url($action)) ?>" class="form action-form-card aid-type-form-card js-prevent-double-submit" novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <section class="form-block aid-type-form-block">
            <div class="form-block-heading">
                <span>1</span>
                <div>
                    <h2>Identificacao do material</h2>
                    <p>Informe o nome exibido nas entregas e a unidade usada para quantificar o item.</p>
                </div>
            </div>

            <div class="form-grid aid-type-fields-grid">
                <label class="field styled-field">
                    <span>Nome do material</span>
                    <input type="text" name="nome" value="<?= h($tipo['nome'] ?? '') ?>" maxlength="180" placeholder="Ex.: Cesta basica, kit higiene, colchao" required autofocus>
                    <?php if (!empty($errors['nome'])): ?>
                        <small class="field-error"><?= h($errors['nome'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <label class="field styled-field">
                    <span>Unidade de medida</span>
                    <input type="text" name="unidade_medida" value="<?= h($tipo['unidade_medida'] ?? '') ?>" maxlength="50" list="aid-type-unit-suggestions" placeholder="kit, cesta, unidade, pacote" required>
                    <datalist id="aid-type-unit-suggestions">
                        <option value="kit"></option>
                        <option value="cesta"></option>
                        <option value="unidade"></option>
                        <option value="pacote"></option>
                        <option value="litros"></option>
                        <option value="valor"></option>
                    </datalist>
                    <?php if (!empty($errors['unidade_medida'])): ?>
                        <small class="field-error"><?= h($errors['unidade_medida'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>
        </section>

        <?php if ($isEditing): ?>
            <section class="form-block aid-type-form-block compact-form-block">
                <div class="form-block-heading">
                    <span>2</span>
                    <div>
                        <h2>Disponibilidade</h2>
                        <p>Tipos inativos deixam de aparecer em novas entregas, mas continuam preservados no historico.</p>
                    </div>
                </div>

                <label class="field styled-field aid-type-status-field">
                    <span>Status</span>
                    <select name="ativo">
                        <option value="1" <?= $ativo ? 'selected' : '' ?>>Ativo</option>
                        <option value="0" <?= !$ativo ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </label>
            </section>
        <?php endif; ?>

        <div class="form-actions action-form-actions">
            <button type="submit" class="primary-button" data-loading-text="Salvando...">
                <span class="button-label">Salvar tipo</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/admin/ajudas')) ?>">Cancelar e voltar</a>
        </div>
    </form>
</section>
