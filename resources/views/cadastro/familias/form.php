<?php
$hasRepresentante = !empty($familia['registrar_representante'])
    || !empty($familia['representante_nome'])
    || !empty($familia['representante_cpf'])
    || !empty($familia['representante_rg'])
    || !empty($familia['representante_telefone']);
$documentos = $documentos ?? [];
?>

<section class="form-shell family-form-shell">
    <div class="section-heading family-form-header">
        <span class="eyebrow">Cadastro de familia</span>
        <h1><?= h($title ?? 'Nova familia') ?></h1>
        <p>Residencia <?= h($residencia['protocolo']) ?> - <?= h($residencia['bairro_comunidade']) ?></p>
    </div>

    <form method="post" action="<?= h(url($action)) ?>" class="form panel-form family-form js-prevent-double-submit" enctype="multipart/form-data" data-family-form novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <section class="form-block family-form-block">
            <div class="form-block-heading">
                <span>Dados do responsavel</span>
                <strong>Identificacao familiar</strong>
            </div>

            <label class="field">
                <span>Responsavel familiar</span>
                <input type="text" name="responsavel_nome" value="<?= h($familia['responsavel_nome'] ?? '') ?>" maxlength="180" required>
                <?php if (!empty($errors['responsavel_nome'])): ?>
                    <small class="field-error"><?= h($errors['responsavel_nome'][0]) ?></small>
                <?php endif; ?>
            </label>

            <div class="form-grid two-columns">
                <label class="field">
                    <span>CPF</span>
                    <input type="text" name="responsavel_cpf" value="<?= h($familia['responsavel_cpf'] ?? '') ?>" maxlength="14" required>
                    <?php if (!empty($errors['responsavel_cpf'])): ?>
                        <small class="field-error"><?= h($errors['responsavel_cpf'][0]) ?></small>
                    <?php endif; ?>
                </label>
                <label class="field">
                    <span>RG</span>
                    <input type="text" name="responsavel_rg" value="<?= h($familia['responsavel_rg'] ?? '') ?>" maxlength="30">
                    <?php if (!empty($errors['responsavel_rg'])): ?>
                        <small class="field-error"><?= h($errors['responsavel_rg'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-grid family-birth-grid">
                <label class="field date-field">
                    <span>Data de nascimento</span>
                    <input type="date" name="data_nascimento" value="<?= h($familia['data_nascimento'] ?? '') ?>">
                    <?php if (!empty($errors['data_nascimento'])): ?>
                        <small class="field-error"><?= h($errors['data_nascimento'][0]) ?></small>
                    <?php endif; ?>
                </label>
                <div class="field">
                    <span>Quantidade de integrantes</span>
                    <div class="quantity-stepper" data-quantity-stepper>
                        <button type="button" class="quantity-stepper-button" data-quantity-decrement aria-label="Diminuir quantidade">-</button>
                        <input type="number" name="quantidade_integrantes" value="<?= h($familia['quantidade_integrantes'] ?? '1') ?>" min="1" required data-quantity-input>
                        <button type="button" class="quantity-stepper-button" data-quantity-increment aria-label="Aumentar quantidade">+</button>
                    </div>
                    <?php if (!empty($errors['quantidade_integrantes'])): ?>
                        <small class="field-error"><?= h($errors['quantidade_integrantes'][0]) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-grid two-columns">
                <label class="field">
                    <span>Telefone</span>
                    <input type="text" name="telefone" value="<?= h($familia['telefone'] ?? '') ?>" maxlength="30">
                </label>
                <label class="field">
                    <span>E-mail</span>
                    <input type="email" name="email" value="<?= h($familia['email'] ?? '') ?>" maxlength="180">
                    <?php if (!empty($errors['email'])): ?>
                        <small class="field-error"><?= h($errors['email'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>
        </section>

        <section class="form-block family-form-block">
            <div class="form-block-heading">
                <span>Selecao multipla</span>
                <strong>Vulnerabilidades</strong>
            </div>

            <div class="vulnerability-picker">
                <label>
                    <input type="checkbox" name="possui_criancas" value="1" <?= !empty($familia['possui_criancas']) ? 'checked' : '' ?>>
                    <span>Criancas</span>
                </label>
                <label>
                    <input type="checkbox" name="possui_idosos" value="1" <?= !empty($familia['possui_idosos']) ? 'checked' : '' ?>>
                    <span>Idosos</span>
                </label>
                <label>
                    <input type="checkbox" name="possui_pcd" value="1" <?= !empty($familia['possui_pcd']) ? 'checked' : '' ?>>
                    <span>PCD</span>
                </label>
                <label>
                    <input type="checkbox" name="possui_gestantes" value="1" <?= !empty($familia['possui_gestantes']) ? 'checked' : '' ?>>
                    <span>Gestantes</span>
                </label>
            </div>
        </section>

        <section class="form-block family-form-block">
            <div class="representative-toggle">
                <div>
                    <span>Representante</span>
                    <strong>Registrar representante, se houver</strong>
                </div>
                <label class="switch-control">
                    <input type="checkbox" name="registrar_representante" value="1" data-representative-toggle <?= $hasRepresentante ? 'checked' : '' ?>>
                </label>
            </div>

            <div class="representative-fields" data-representative-fields <?= $hasRepresentante ? '' : 'hidden' ?>>
                <label class="field">
                    <span>Nome do representante</span>
                    <input type="text" name="representante_nome" value="<?= h($familia['representante_nome'] ?? '') ?>" maxlength="180" data-representative-input>
                </label>

                <div class="form-grid two-columns">
                    <label class="field">
                        <span>CPF do representante</span>
                        <input type="text" name="representante_cpf" value="<?= h($familia['representante_cpf'] ?? '') ?>" maxlength="14" data-representative-input>
                        <?php if (!empty($errors['representante_cpf'])): ?>
                            <small class="field-error"><?= h($errors['representante_cpf'][0]) ?></small>
                        <?php endif; ?>
                    </label>
                    <label class="field">
                        <span>RG do representante</span>
                        <input type="text" name="representante_rg" value="<?= h($familia['representante_rg'] ?? '') ?>" maxlength="30" data-representative-input>
                    </label>
                </div>

                <label class="field">
                    <span>Telefone do representante</span>
                    <input type="text" name="representante_telefone" value="<?= h($familia['representante_telefone'] ?? '') ?>" maxlength="30" data-representative-input>
                </label>
            </div>
        </section>

        <section class="form-block family-form-block">
            <div class="form-block-heading">
                <span>Arquivos</span>
                <strong>Anexar documentos</strong>
            </div>

            <?php if ($documentos !== []): ?>
                <div class="family-existing-docs">
                    <div class="family-existing-docs-heading">
                        <span>Anexos atuais</span>
                        <strong><?= h(count($documentos)) ?> arquivo(s)</strong>
                    </div>
                    <div class="family-doc-list family-existing-doc-list">
                        <?php foreach ($documentos as $documento): ?>
                            <?php
                            $documentoUrl = url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . ($familia['id'] ?? 0) . '/documentos/' . $documento['id']);
                            $isImage = str_starts_with((string) ($documento['mime_type'] ?? ''), 'image/');
                            ?>
                            <div class="family-existing-doc-item">
                                <div class="family-doc-preview">
                                    <?php if ($isImage): ?>
                                        <img src="<?= h($documentoUrl) ?>" alt="Anexo <?= h($documento['nome_original']) ?>">
                                    <?php else: ?>
                                        <span class="family-doc-preview-icon"><?= h(strtoupper((string) ($documento['extensao'] ?? 'PDF'))) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="family-doc-info">
                                    <span><?= h($documento['nome_original']) ?></span>
                                    <small><?= h(number_format(((int) $documento['tamanho_bytes']) / 1024, 1, ',', '.')) ?> KB - <?= h(date('d/m/Y H:i', strtotime((string) $documento['criado_em']))) ?></small>
                                </div>
                                <div class="family-existing-doc-actions">
                                    <button class="family-doc-view-link" type="button" data-family-doc-open data-doc-src="<?= h($documentoUrl) ?>" data-doc-title="<?= h($documento['nome_original']) ?>" data-doc-kind="<?= $isImage ? 'image' : 'document' ?>">Visualizar</button>
                                    <label class="family-doc-remove-action">
                                        <input type="checkbox" name="remover_documentos[]" value="<?= h($documento['id']) ?>" data-family-doc-remove>
                                        <span>Remover</span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="family-doc-upload photo-upload" data-family-doc-upload>
                <input class="file-input-native" id="documentos-familia" type="file" name="documentos[]" accept="image/jpeg,image/png,application/pdf,image/*" capture="environment" multiple data-family-doc-input>
                <label class="photo-dropzone family-doc-dropzone" for="documentos-familia" tabindex="0" data-family-doc-dropzone>
                    <strong>Adicionar documentos</strong>
                    <span>Arraste, cole, selecione arquivos ou tire fotos pelo celular.</span>
                </label>
                <div class="family-doc-list" data-family-doc-list hidden></div>
                <small class="field-hint" data-family-doc-status>JPG, PNG ou PDF. Tamanho maximo por arquivo: 5 MB.</small>
            </div>
            <?php if (!empty($errors['documentos'])): ?>
                <small class="field-error"><?= h($errors['documentos'][0]) ?></small>
            <?php endif; ?>
        </section>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Processando...">
                <span class="button-label"><?= h($submitLabel ?? 'Salvar familia') ?></span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/cadastros/residencias/' . $residencia['id'])) ?>">Cancelar</a>
        </div>
    </form>
</section>

<dialog class="family-doc-modal" data-family-doc-modal aria-labelledby="family-doc-modal-title">
    <form method="dialog" class="family-doc-modal-close-form">
        <button type="submit" class="family-doc-modal-close" aria-label="Fechar documento">Fechar</button>
    </form>
    <div class="family-doc-modal-content">
        <h2 id="family-doc-modal-title" data-family-doc-modal-title>Documento</h2>
        <img alt="Documento ampliado" data-family-doc-modal-image hidden>
        <iframe title="Documento anexado" data-family-doc-modal-frame hidden></iframe>
    </div>
</dialog>
