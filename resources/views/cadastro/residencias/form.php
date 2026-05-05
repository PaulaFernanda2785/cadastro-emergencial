<?php
$offlineTokensJson = json_encode($offlineTokens ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$bairroOptionsJson = json_encode(array_values($bairroOptions ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$useOfflineQueue = (bool) ($useOfflineQueue ?? true);
$submitLabel = $submitLabel ?? 'Salvar residência';
$cancelUrl = $cancelUrl ?? '/acao/' . $acao['token_publico'];
$isEditing = !empty($residencia['id']);
$appConfig = require BASE_PATH . '/config/app.php';
$appTimezone = (string) ($appConfig['timezone'] ?? 'America/Belem');
$documentos = $documentos ?? [];
$residenciaId = (int) ($residencia['id'] ?? 0);
$residenceImageDocuments = array_values(array_filter($documentos, static fn (array $documento): bool =>
    $residenciaId > 0
    && (int) ($documento['residencia_id'] ?? 0) === $residenciaId
    && empty($documento['familia_id'])
    && str_starts_with((string) ($documento['mime_type'] ?? ''), 'image/')
));
$existingMainPhotos = array_values(array_filter($residenceImageDocuments, static fn (array $documento): bool =>
    (string) ($documento['tipo_documento'] ?? '') === 'foto_georreferenciada'
));
$existingExtraPhotos = array_values(array_filter($residenceImageDocuments, static fn (array $documento): bool =>
    (string) ($documento['tipo_documento'] ?? '') === 'foto_residencia_extra'
));
$existingMainPhoto = $existingMainPhotos[0] ?? null;
?>

<section class="records-page residence-edit-page">
    <header class="action-form-header records-header">
        <div>
            <span class="eyebrow"><?= $isEditing ? 'Editar cadastro' : 'Cadastro de campo' ?></span>
            <h1><?= h($title ?? 'Nova residência') ?></h1>
            <p><?= h($acao['municipio_nome']) ?> / <?= h($acao['uf']) ?> - <?= h($acao['localidade']) ?> - <?= h($acao['tipo_evento']) ?></p>
        </div>
        <?php if ($isEditing): ?>
            <a class="secondary-button residence-action-button" href="<?= h(url($cancelUrl)) ?>">Voltar ao detalhe</a>
        <?php endif; ?>
    </header>

    <section class="records-summary-grid" aria-label="Resumo da residência">
        <article class="records-summary-card">
            <span>Protocolo</span>
            <strong><?= h($residencia['protocolo'] ?? 'Novo') ?></strong>
            <small><?= $isEditing ? 'Cadastro em edição.' : 'Gerado após salvar.' ?></small>
        </article>
        <article class="records-summary-card">
            <span>Município</span>
            <strong><?= h($acao['municipio_nome']) ?> / <?= h($acao['uf']) ?></strong>
            <small>Área da ação emergencial.</small>
        </article>
        <article class="records-summary-card">
            <span>Foto principal</span>
            <strong><?= $existingMainPhoto !== null ? 'Registrada' : 'Pendente' ?></strong>
            <small><?= $existingMainPhoto !== null ? 'Pode ampliar, remover ou substituir.' : 'Opcional, com carimbo ao enviar.' ?></small>
        </article>
        <article class="records-summary-card">
            <span>Fotos extras</span>
            <strong><?= h(count($existingExtraPhotos)) ?> / 3</strong>
            <small>Limite de fotos adicionais da residência.</small>
        </article>
    </section>

    <form
        method="post"
        action="<?= h(url($action)) ?>"
        class="form panel-form residence-edit-form js-prevent-double-submit"
        enctype="multipart/form-data"
        data-residence-form
        data-geolocation-form
        <?= $useOfflineQueue ? 'data-offline-queue-form' : '' ?>
        data-offline-tokens='<?= h($offlineTokensJson ?: '[]') ?>'
        data-community-options='<?= h($bairroOptionsJson ?: '[]') ?>'
        data-action-municipality="<?= h($acao['municipio_nome']) ?>"
        data-action-state="<?= h($acao['uf']) ?>"
        data-action-locality="<?= h($acao['localidade']) ?>"
        data-action-event="<?= h($acao['tipo_evento']) ?>"
        data-app-timezone="<?= h($appTimezone) ?>"
        novalidate
    >
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <?php if ($useOfflineQueue): ?>
        <div class="offline-sync-panel" data-offline-panel hidden>
            <strong data-offline-title>Cadastro offline disponível</strong>
            <span data-offline-message>Sem conexão com o servidor. O cadastro será salvo neste celular e enviado quando a conexão voltar.</span>
            <button type="button" class="secondary-button" data-offline-sync>Sincronizar agora</button>
        </div>
        <?php endif; ?>

        <section class="residence-form-block">
            <div class="form-block-heading">
                <h2>Localização e fotos</h2>
            </div>

            <div class="field photo-upload" data-photo-upload data-photo-logo-src="<?= h(asset('images/logo-cadastro-emergencial-app.png')) ?>">
                <span>Foto georreferenciada da residência</span>
                <?php if ($existingMainPhoto !== null): ?>
                    <?php $mainPhotoUrl = url('/cadastros/residencias/' . $residenciaId . '/documentos/' . $existingMainPhoto['id']); ?>
                    <div class="photo-preview existing-photo-preview" data-existing-photo-card>
                        <img src="<?= h($mainPhotoUrl) ?>" alt="Foto principal registrada">
                        <div class="photo-preview-info">
                            <span><?= h($existingMainPhoto['nome_original']) ?></span>
                            <input type="checkbox" name="remover_documentos[]" value="<?= h($existingMainPhoto['id']) ?>" data-existing-photo-remove-input hidden>
                            <div class="photo-preview-actions">
                                <button type="button" class="secondary-button photo-preview-button" data-existing-photo-open="<?= h($mainPhotoUrl) ?>">Ampliar foto</button>
                                <button type="button" class="secondary-button photo-preview-button danger-outline-button" data-existing-photo-remove>Remover foto</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <input class="file-input-native" id="foto-georreferenciada" type="file" name="foto_georreferenciada" accept="image/*" data-photo-input>
                <label class="photo-dropzone" for="foto-georreferenciada" data-photo-dropzone tabindex="0">
                    <strong data-photo-title><?= $existingMainPhoto !== null ? 'Substituir foto principal' : 'Selecionar foto' ?></strong>
                    <span data-photo-description>Arraste, cole, busque nos arquivos ou tire uma foto pela câmera do celular.</span>
                </label>
                <div class="photo-preview" data-photo-preview hidden>
                    <img alt="Prévia da foto georreferenciada" data-photo-preview-image>
                    <div class="photo-preview-info">
                        <span data-photo-preview-name></span>
                        <div class="photo-preview-actions">
                            <button type="button" class="secondary-button photo-preview-button" data-photo-open-preview>Ampliar foto</button>
                            <button type="button" class="secondary-button photo-preview-button" data-photo-clear>Remover foto</button>
                        </div>
                    </div>
                </div>
                <small class="field-hint" data-photo-status>Ao enviar, a foto receberá localidade, endereço, latitude, longitude, data e hora.</small>
                <?php if (!empty($errors['foto_georreferenciada'])): ?>
                    <small class="field-error"><?= h($errors['foto_georreferenciada'][0]) ?></small>
                <?php endif; ?>
            </div>

            <div class="extra-residence-photos" data-extra-residence-photos data-max-files="3" data-existing-files="<?= h(count($existingExtraPhotos)) ?>">
                <div class="representative-toggle extra-residence-photos-toggle">
                    <div>
                        <span>Fotos da residência</span>
                        <strong>Gerenciar até 3 fotos adicionais</strong>
                    </div>
                    <label class="switch-control">
                        <input type="checkbox" name="anexar_fotos_residencia" value="1" data-extra-photos-toggle>
                    </label>
                </div>

                <?php if ($existingExtraPhotos !== []): ?>
                    <div class="existing-extra-photo-list" data-existing-extra-photo-list>
                        <?php foreach ($existingExtraPhotos as $fotoExtra): ?>
                            <?php $fotoExtraUrl = url('/cadastros/residencias/' . $residenciaId . '/documentos/' . $fotoExtra['id']); ?>
                            <div class="photo-preview extra-photo-item" data-existing-extra-photo-card>
                                <img src="<?= h($fotoExtraUrl) ?>" alt="Foto adicional registrada">
                                <div class="photo-preview-info">
                                    <span><?= h($fotoExtra['nome_original']) ?></span>
                                    <input type="checkbox" name="remover_documentos[]" value="<?= h($fotoExtra['id']) ?>" data-existing-extra-photo-remove-input hidden>
                                    <div class="photo-preview-actions">
                                        <button type="button" class="secondary-button photo-preview-button" data-existing-extra-photo-open="<?= h($fotoExtraUrl) ?>">Ampliar foto</button>
                                        <button type="button" class="secondary-button photo-preview-button danger-outline-button" data-existing-extra-photo-remove>Remover foto</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="extra-residence-photos-fields" data-extra-photos-fields hidden>
                    <input class="file-input-native" id="fotos-residencia" type="file" name="fotos_residencia[]" accept="image/*" multiple data-extra-photos-input disabled>
                    <label class="photo-dropzone" for="fotos-residencia" data-extra-photos-dropzone tabindex="0">
                        <strong>Adicionar fotos da residência</strong>
                        <span>As fotos extras recebem o mesmo carimbo da foto principal, mas não alteram os campos de localização do formulário.</span>
                    </label>
                    <div class="extra-photo-list" data-extra-photos-list hidden></div>
                    <small class="field-hint" data-extra-photos-status>
                        <?= count($existingExtraPhotos) > 0
                            ? h(count($existingExtraPhotos) . ' de 3 fotos extras cadastradas. Remova uma foto para substituir ou anexe nos espaços livres.')
                            : 'Opcional. Limite de 3 fotos extras por residência.' ?>
                    </small>
                    <?php if (!empty($errors['fotos_residencia'])): ?>
                        <small class="field-error"><?= h($errors['fotos_residencia'][0]) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-grid location-fields-grid">
                <div class="inline-action-panel">
                    <button type="button" class="secondary-button" data-geolocation-button>Capturar localização atual</button>
                    <span data-geolocation-status></span>
                </div>

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
        </section>

        <section class="residence-form-block">
            <div class="form-block-heading">
                <h2>Endereço</h2>
            </div>

            <div class="form-grid two-columns">
                <label class="field smart-field" data-community-field>
                    <span>Bairro/comunidade</span>
                    <input type="text" name="bairro_comunidade" value="<?= h($residencia['bairro_comunidade'] ?? '') ?>" maxlength="180" required autocomplete="off" data-community-input placeholder="Busque ou informe">
                    <div class="smart-suggestions" data-community-suggestions hidden></div>
                    <?php if (!empty($errors['bairro_comunidade'])): ?>
                        <small class="field-error"><?= h($errors['bairro_comunidade'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span>Complemento</span>
                    <input type="text" name="complemento" value="<?= h($residencia['complemento'] ?? '') ?>" maxlength="180">
                    <?php if (!empty($errors['complemento'])): ?>
                        <small class="field-error"><?= h($errors['complemento'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <label class="field">
                <span>Endereço completo</span>
                <input type="text" name="endereco" value="<?= h($residencia['endereco'] ?? '') ?>" maxlength="255" required data-address>
                <?php if (!empty($errors['endereco'])): ?>
                    <small class="field-error"><?= h($errors['endereco'][0]) ?></small>
                <?php endif; ?>
            </label>
        </section>

        <section class="residence-form-block">
            <div class="form-block-heading">
                <h2>Situação do imóvel</h2>
            </div>

            <div class="form-grid residence-choice-grid">
                <fieldset class="choice-field">
                    <legend>Imóvel</legend>
                    <div class="choice-options">
                        <?php foreach (residencia_imovel_options() as $value => $label): ?>
                            <label class="choice-option">
                                <input type="radio" name="imovel" value="<?= h($value) ?>" required <?= (string) ($residencia['imovel'] ?? '') === $value ? 'checked' : '' ?>>
                                <span><?= h($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($errors['imovel'])): ?>
                        <small class="field-error"><?= h($errors['imovel'][0]) ?></small>
                    <?php endif; ?>
                </fieldset>

                <fieldset class="choice-field">
                    <legend>Condição da residência</legend>
                    <div class="choice-options">
                        <?php foreach (residencia_condicao_options() as $value => $label): ?>
                            <label class="choice-option">
                                <input type="radio" name="condicao_residencia" value="<?= h($value) ?>" required <?= (string) ($residencia['condicao_residencia'] ?? '') === $value ? 'checked' : '' ?>>
                                <span><?= h($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($errors['condicao_residencia'])): ?>
                        <small class="field-error"><?= h($errors['condicao_residencia'][0]) ?></small>
                    <?php endif; ?>
                </fieldset>
            </div>
        </section>

        <section class="residence-form-block compact-residence-block">
            <div class="field residence-family-count-field">
                <span>Quantidade de famílias residentes</span>
                <div class="quantity-stepper" data-quantity-stepper>
                    <button type="button" class="quantity-stepper-button" data-quantity-decrement aria-label="Diminuir quantidade">-</button>
                    <input type="number" name="quantidade_familias" value="<?= h($residencia['quantidade_familias'] ?? '1') ?>" min="1" required data-quantity-input>
                    <button type="button" class="quantity-stepper-button" data-quantity-increment aria-label="Aumentar quantidade">+</button>
                </div>
                <?php if (!empty($errors['quantidade_familias'])): ?>
                    <small class="field-error"><?= h($errors['quantidade_familias'][0]) ?></small>
                <?php endif; ?>
            </div>
        </section>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Processando...">
                <span class="button-label"><?= h($submitLabel) ?></span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url($cancelUrl)) ?>">Cancelar</a>
        </div>
    </form>
</section>
