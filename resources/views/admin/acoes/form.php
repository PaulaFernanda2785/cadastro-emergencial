<?php
$territoryPayload = [
    'municipios' => $municipiosTerritoriais,
    'localidades' => $localidadesPorMunicipio,
];
$territoryJson = json_encode($territoryPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>

<section class="action-form-page">
    <header class="action-form-header">
        <div>
            <span class="eyebrow">Administração</span>
            <h1><?= h($title) ?></h1>
            <p>Configure a ação, selecione o município e informe a localidade de atendimento.</p>
        </div>
        <a class="secondary-button action-back-link" href="<?= h(url('/admin/acoes')) ?>">Voltar para ações</a>
    </header>

    <form method="post" action="<?= h(url($action)) ?>" class="form action-form-card js-prevent-double-submit js-action-form" novalidate data-territory='<?= h($territoryJson ?: '{}') ?>'>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <section class="form-block">
            <div class="form-block-heading">
                <span>1</span>
                <div>
                    <h2>Local da ação</h2>
                    <p>Escolha o estado, busque o município e defina a localidade.</p>
                </div>
            </div>

            <div class="form-grid action-location-grid">
                <label class="field styled-field">
                    <span>Estado</span>
                    <select name="estado" data-state-select required>
                        <option value="">Selecione</option>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= h($estado['uf']) ?>" <?= (string) ($acao['estado'] ?? '') === (string) $estado['uf'] ? 'selected' : '' ?>>
                                <?= h($estado['nome']) ?> / <?= h($estado['uf']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['estado'])): ?>
                        <small class="field-error"><?= h($errors['estado'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <label class="field styled-field">
                    <span>Município</span>
                    <input type="text" name="municipio_nome" value="<?= h($acao['municipio_nome'] ?? '') ?>" maxlength="180" list="municipios-territoriais" data-municipality-input required autocomplete="off" placeholder="Digite o município">
                    <input type="hidden" name="municipio_codigo_ibge" value="<?= h($acao['municipio_codigo_ibge'] ?? '') ?>" data-municipality-code>
                    <input type="hidden" name="municipio_id" value="<?= h($acao['municipio_id'] ?? '') ?>">
                    <datalist id="municipios-territoriais" data-municipality-list></datalist>
                    <?php if (!empty($errors['municipio_codigo_ibge'])): ?>
                        <small class="field-error"><?= h($errors['municipio_codigo_ibge'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <label class="field styled-field">
                <span>Localidade, bairro ou comunidade</span>
                <input type="text" name="localidade" value="<?= h($acao['localidade'] ?? '') ?>" maxlength="180" list="localidades-cadastradas" data-locality-input required autocomplete="off" placeholder="Busque uma existente ou informe uma nova">
                <datalist id="localidades-cadastradas" data-locality-list></datalist>
                <?php if (!empty($errors['localidade'])): ?>
                    <small class="field-error"><?= h($errors['localidade'][0]) ?></small>
                <?php endif; ?>
            </label>
        </section>

        <section class="form-block">
            <div class="form-block-heading">
                <span>2</span>
                <div>
                    <h2>Dados do evento</h2>
                    <p>Informe o tipo de ocorrência, a data e a situação da ação.</p>
                </div>
            </div>

            <label class="field styled-field">
                <span>Tipo de evento</span>
                <input type="text" name="tipo_evento" value="<?= h($acao['tipo_evento'] ?? '') ?>" maxlength="180" placeholder="Ex.: enxurradas, inundações, vendaval" required>
                <?php if (!empty($errors['tipo_evento'])): ?>
                    <small class="field-error"><?= h($errors['tipo_evento'][0]) ?></small>
                <?php endif; ?>
            </label>

            <div class="form-grid two-columns">
                <label class="field styled-field">
                    <span>Data do evento</span>
                    <input type="date" name="data_evento" value="<?= h($acao['data_evento'] ?? date('Y-m-d')) ?>" required>
                    <?php if (!empty($errors['data_evento'])): ?>
                        <small class="field-error"><?= h($errors['data_evento'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <label class="field styled-field">
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
            </div>
        </section>

        <?php if (!empty($acao['token_publico'])): ?>
            <section class="form-block compact-form-block">
                <div class="readonly-field action-link-field">
                <span>Link público da ação</span>
                <a href="<?= h(url('/acao/' . $acao['token_publico'])) ?>" target="_blank" rel="noopener"><?= h(url('/acao/' . $acao['token_publico'])) ?></a>
            </div>
        </section>
        <?php endif; ?>

        <div class="form-actions action-form-actions">
            <button type="submit" class="primary-button" data-loading-text="Salvando...">
                <span class="button-label">Salvar ação</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/admin/acoes')) ?>">Cancelar e voltar para ações</a>
        </div>
    </form>
</section>
