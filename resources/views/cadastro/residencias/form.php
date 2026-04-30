<?php
$offlineTokensJson = json_encode($offlineTokens ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>

<section class="form-shell">
    <div class="section-heading">
        <span class="eyebrow">Cadastro de campo</span>
        <h1>Nova residencia</h1>
        <p><?= h($acao['municipio_nome']) ?> / <?= h($acao['uf']) ?> - <?= h($acao['localidade']) ?> - <?= h($acao['tipo_evento']) ?></p>
    </div>

    <form method="post" action="<?= h(url($action)) ?>" class="form panel-form js-prevent-double-submit" enctype="multipart/form-data" data-geolocation-form data-offline-queue-form data-offline-tokens='<?= h($offlineTokensJson ?: '[]') ?>' novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <div class="offline-sync-panel" data-offline-panel hidden>
            <strong data-offline-title>Cadastro offline disponível</strong>
            <span data-offline-message>Sem conexão com o servidor. O cadastro será salvo neste celular e enviado quando a conexão voltar.</span>
            <button type="button" class="secondary-button" data-offline-sync>Sincronizar agora</button>
        </div>

        <label class="field">
            <span>Bairro/comunidade</span>
            <input type="text" name="bairro_comunidade" value="<?= h($residencia['bairro_comunidade'] ?? '') ?>" maxlength="180" required>
            <?php if (!empty($errors['bairro_comunidade'])): ?>
                <small class="field-error"><?= h($errors['bairro_comunidade'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Endereco completo</span>
            <input type="text" name="endereco" value="<?= h($residencia['endereco'] ?? '') ?>" maxlength="255" required>
            <?php if (!empty($errors['endereco'])): ?>
                <small class="field-error"><?= h($errors['endereco'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Complemento</span>
            <input type="text" name="complemento" value="<?= h($residencia['complemento'] ?? '') ?>" maxlength="180">
            <?php if (!empty($errors['complemento'])): ?>
                <small class="field-error"><?= h($errors['complemento'][0]) ?></small>
            <?php endif; ?>
        </label>

        <div class="form-grid two-columns">
            <label class="field">
                <span>Latitude</span>
                <input type="text" name="latitude" value="<?= h($residencia['latitude'] ?? '') ?>" inputmode="decimal" placeholder="-1.455833" data-latitude>
                <?php if (!empty($errors['latitude'])): ?>
                    <small class="field-error"><?= h($errors['latitude'][0]) ?></small>
                <?php endif; ?>
            </label>

            <label class="field">
                <span>Longitude</span>
                <input type="text" name="longitude" value="<?= h($residencia['longitude'] ?? '') ?>" inputmode="decimal" placeholder="-48.503887" data-longitude>
                <?php if (!empty($errors['longitude'])): ?>
                    <small class="field-error"><?= h($errors['longitude'][0]) ?></small>
                <?php endif; ?>
            </label>
        </div>

        <div class="inline-action-panel">
            <button type="button" class="secondary-button" data-geolocation-button>Capturar localizacao atual</button>
            <span data-geolocation-status>Latitude e longitude tambem podem ser preenchidas manualmente.</span>
        </div>

        <label class="field">
            <span>Foto georreferenciada da residencia</span>
            <input type="file" name="foto_georreferenciada" accept="image/jpeg,image/png">
            <small class="field-hint">Formatos permitidos: JPG ou PNG. Tamanho maximo: 5 MB.</small>
            <?php if (!empty($errors['foto_georreferenciada'])): ?>
                <small class="field-error"><?= h($errors['foto_georreferenciada'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Quantidade de familias residentes</span>
            <input type="number" name="quantidade_familias" value="<?= h($residencia['quantidade_familias'] ?? '1') ?>" min="1" required>
            <?php if (!empty($errors['quantidade_familias'])): ?>
                <small class="field-error"><?= h($errors['quantidade_familias'][0]) ?></small>
            <?php endif; ?>
        </label>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Processando...">
                <span class="button-label">Salvar residencia</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/acao/' . $acao['token_publico'])) ?>">Cancelar</a>
        </div>
    </form>
</section>
