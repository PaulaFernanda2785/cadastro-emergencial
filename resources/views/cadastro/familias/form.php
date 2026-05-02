<?php
$hasRepresentante = !empty($familia['registrar_representante'])
    || !empty($familia['representante_nome'])
    || !empty($familia['representante_cpf'])
    || !empty($familia['representante_rg'])
    || !empty($familia['representante_orgao_expedidor'])
    || !empty($familia['representante_data_nascimento'])
    || !empty($familia['representante_sexo'])
    || !empty($familia['representante_telefone']);
$hasBeneficio = !empty($familia['recebe_beneficio_social']);
$documentos = $documentos ?? [];
$familiaId = (int) ($familia['id'] ?? 0);

$optionSelected = static function (array $familia, string $field, string $value): string {
    return (string) ($familia[$field] ?? '') === $value ? 'selected' : '';
};
?>

<section class="records-page residence-edit-page family-edit-page">
    <div class="action-form-header records-header">
        <div>
            <span class="eyebrow">Cadastro de familia</span>
            <h1><?= h($title ?? 'Nova familia') ?></h1>
            <p>Residencia <?= h($residencia['protocolo']) ?> - <?= h($residencia['bairro_comunidade']) ?></p>
        </div>
        <a class="secondary-button action-back-link" href="<?= h(url('/cadastros/residencias/' . $residencia['id'])) ?>">Voltar para residencia</a>
    </div>

    <div class="records-summary-grid family-summary-grid" aria-label="Resumo da familia">
        <div class="records-summary-card">
            <span>Residencia</span>
            <strong><?= h($residencia['protocolo']) ?></strong>
            <small><?= h($residencia['bairro_comunidade'] ?? 'Sem bairro') ?></small>
        </div>
        <div class="records-summary-card">
            <span>Responsavel</span>
            <strong><?= h($familia['responsavel_nome'] ?: 'Novo cadastro') ?></strong>
            <small><?= h($familia['responsavel_cpf'] ?: 'CPF pendente') ?></small>
        </div>
        <div class="records-summary-card">
            <span>Integrantes</span>
            <strong><?= h($familia['quantidade_integrantes'] ?? '1') ?></strong>
            <small>pessoa(s) na familia</small>
        </div>
        <div class="records-summary-card">
            <span>Arquivos</span>
            <strong><?= h(count($documentos)) ?></strong>
            <small>anexo(s) atuais</small>
        </div>
    </div>

    <form method="post" action="<?= h(url($action)) ?>" class="form panel-form residence-edit-form family-edit-form js-prevent-double-submit" enctype="multipart/form-data" data-family-form novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <section class="form-block residence-form-block family-form-block family-files-first">
            <div class="form-block-heading">
                <span>Arquivos</span>
                <strong>Documentos pessoais</strong>
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
                            $documentoUrl = url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familiaId . '/documentos/' . $documento['id']);
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

            <div class="family-doc-upload-grid family-doc-upload-grid-single">
                <div class="family-doc-upload photo-upload" data-family-doc-upload data-doc-target="responsavel">
                    <input class="file-input-native" id="documentos-responsavel" type="file" name="documentos[]" accept="image/jpeg,image/png,application/pdf,image/*" capture="environment" multiple data-family-doc-input>
                    <label class="photo-dropzone family-doc-dropzone" for="documentos-responsavel" tabindex="0" data-family-doc-dropzone>
                        <strong>Documentos do responsavel</strong>
                        <span>Anexe ou fotografe RG, CPF ou documento equivalente.</span>
                    </label>
                    <div class="family-doc-list" data-family-doc-list hidden></div>
                    <small class="field-hint" data-family-doc-status>JPG, PNG ou PDF. Tamanho maximo por arquivo: 5 MB.</small>
                </div>
            </div>
            <?php if (!empty($errors['documentos'])): ?>
                <small class="field-error"><?= h($errors['documentos'][0]) ?></small>
            <?php endif; ?>
        </section>

        <section class="form-block residence-form-block family-form-block">
            <div class="form-block-heading">
                <span>Responsavel</span>
                <strong>Identificacao familiar</strong>
            </div>

            <label class="field">
                <span>Nome completo</span>
                <input type="text" name="responsavel_nome" value="<?= h($familia['responsavel_nome'] ?? '') ?>" maxlength="180" required>
                <?php if (!empty($errors['responsavel_nome'])): ?>
                    <small class="field-error"><?= h($errors['responsavel_nome'][0]) ?></small>
                <?php endif; ?>
            </label>

            <div class="form-grid two-columns">
                <label class="field">
                    <span>CPF</span>
                    <input type="text" name="responsavel_cpf" value="<?= h($familia['responsavel_cpf'] ?? '') ?>" maxlength="14" inputmode="numeric" autocomplete="off" data-cpf-input required>
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

            <div class="form-grid three-columns">
                <label class="field">
                    <span>Orgao expedidor</span>
                    <input type="text" name="responsavel_orgao_expedidor" value="<?= h($familia['responsavel_orgao_expedidor'] ?? '') ?>" maxlength="30" required>
                    <?php if (!empty($errors['responsavel_orgao_expedidor'])): ?>
                        <small class="field-error"><?= h($errors['responsavel_orgao_expedidor'][0]) ?></small>
                    <?php endif; ?>
                </label>
                <label class="field date-field">
                    <span>Data de nascimento</span>
                    <input type="date" name="data_nascimento" value="<?= h($familia['data_nascimento'] ?? '') ?>" required>
                    <?php if (!empty($errors['data_nascimento'])): ?>
                        <small class="field-error"><?= h($errors['data_nascimento'][0]) ?></small>
                    <?php endif; ?>
                </label>
                <label class="field">
                    <span>Sexo</span>
                    <select name="responsavel_sexo" required>
                        <option value="">Selecione</option>
                        <option value="feminino" <?= $optionSelected($familia, 'responsavel_sexo', 'feminino') ?>>Feminino</option>
                        <option value="masculino" <?= $optionSelected($familia, 'responsavel_sexo', 'masculino') ?>>Masculino</option>
                        <option value="outro" <?= $optionSelected($familia, 'responsavel_sexo', 'outro') ?>>Outro</option>
                        <option value="nao_informado" <?= $optionSelected($familia, 'responsavel_sexo', 'nao_informado') ?>>Nao informado</option>
                    </select>
                    <?php if (!empty($errors['responsavel_sexo'])): ?>
                        <small class="field-error"><?= h($errors['responsavel_sexo'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <div class="form-grid three-columns">
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
        </section>

        <section class="form-block residence-form-block family-form-block">
            <div class="form-block-heading">
                <span>Condicao</span>
                <strong>Perfil familiar</strong>
            </div>

            <div class="form-grid two-columns">
                <label class="field">
                    <span>Renda familiar</span>
                    <select name="renda_familiar" required>
                        <option value="">Selecione</option>
                        <option value="0_3_salarios" <?= $optionSelected($familia, 'renda_familiar', '0_3_salarios') ?>>0 a 3 salarios</option>
                        <option value="acima_3_salarios" <?= $optionSelected($familia, 'renda_familiar', 'acima_3_salarios') ?>>Acima de 3 salarios</option>
                    </select>
                    <?php if (!empty($errors['renda_familiar'])): ?>
                        <small class="field-error"><?= h($errors['renda_familiar'][0]) ?></small>
                    <?php endif; ?>
                </label>
                <label class="field">
                    <span>Situacao da familia</span>
                    <select name="situacao_familia" required>
                        <option value="">Selecione</option>
                        <option value="desabrigado" <?= $optionSelected($familia, 'situacao_familia', 'desabrigado') ?>>Desabrigado</option>
                        <option value="desalojado" <?= $optionSelected($familia, 'situacao_familia', 'desalojado') ?>>Desalojado</option>
                        <option value="aluguel_social" <?= $optionSelected($familia, 'situacao_familia', 'aluguel_social') ?>>Aluguel social</option>
                        <option value="permanece_residencia" <?= $optionSelected($familia, 'situacao_familia', 'permanece_residencia') ?>>Permanece na residencia</option>
                    </select>
                    <?php if (!empty($errors['situacao_familia'])): ?>
                        <small class="field-error"><?= h($errors['situacao_familia'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <label class="field">
                <span>Perdas de bens moveis</span>
                <textarea name="perdas_bens_moveis" rows="3" maxlength="1000"><?= h($familia['perdas_bens_moveis'] ?? '') ?></textarea>
                <?php if (!empty($errors['perdas_bens_moveis'])): ?>
                    <small class="field-error"><?= h($errors['perdas_bens_moveis'][0]) ?></small>
                <?php endif; ?>
            </label>

            <div class="representative-toggle family-benefit-toggle">
                <div>
                    <span>Beneficio social</span>
                    <strong>Recebe beneficio social?</strong>
                </div>
                <label class="switch-control">
                    <input type="checkbox" name="recebe_beneficio_social" value="1" data-benefit-toggle <?= $hasBeneficio ? 'checked' : '' ?>>
                </label>
            </div>
            <div class="family-benefit-fields" data-benefit-fields <?= $hasBeneficio ? '' : 'hidden' ?>>
                <label class="field">
                    <span>Se sim, qual?</span>
                    <input type="text" name="beneficio_social_nome" value="<?= h($familia['beneficio_social_nome'] ?? '') ?>" maxlength="180" data-benefit-input>
                    <?php if (!empty($errors['beneficio_social_nome'])): ?>
                        <small class="field-error"><?= h($errors['beneficio_social_nome'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>
        </section>

        <section class="form-block residence-form-block family-form-block">
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

        <section class="form-block residence-form-block family-form-block">
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
                <div class="family-doc-upload photo-upload representative-doc-upload" data-family-doc-upload data-doc-target="representante">
                    <input class="file-input-native" id="documentos-representante" type="file" name="documentos[]" accept="image/jpeg,image/png,application/pdf,image/*" capture="environment" multiple data-family-doc-input>
                    <label class="photo-dropzone family-doc-dropzone" for="documentos-representante" tabindex="0" data-family-doc-dropzone>
                        <strong>Documentos do representante</strong>
                        <span>Anexe RG, CPF ou documento equivalente.</span>
                    </label>
                    <div class="family-doc-list" data-family-doc-list hidden></div>
                    <small class="field-hint" data-family-doc-status>JPG, PNG ou PDF. Tamanho maximo por arquivo: 5 MB.</small>
                </div>

                <label class="field">
                    <span>Nome completo</span>
                    <input type="text" name="representante_nome" value="<?= h($familia['representante_nome'] ?? '') ?>" maxlength="180" required data-representative-input>
                    <?php if (!empty($errors['representante_nome'])): ?>
                        <small class="field-error"><?= h($errors['representante_nome'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <div class="form-grid two-columns">
                    <label class="field">
                        <span>CPF</span>
                        <input type="text" name="representante_cpf" value="<?= h($familia['representante_cpf'] ?? '') ?>" maxlength="14" inputmode="numeric" autocomplete="off" required data-cpf-input data-representative-input>
                        <?php if (!empty($errors['representante_cpf'])): ?>
                            <small class="field-error"><?= h($errors['representante_cpf'][0]) ?></small>
                        <?php endif; ?>
                    </label>
                    <label class="field">
                        <span>RG</span>
                        <input type="text" name="representante_rg" value="<?= h($familia['representante_rg'] ?? '') ?>" maxlength="30" data-representative-input>
                        <?php if (!empty($errors['representante_rg'])): ?>
                            <small class="field-error"><?= h($errors['representante_rg'][0]) ?></small>
                        <?php endif; ?>
                    </label>
                </div>

                <div class="form-grid three-columns">
                    <label class="field">
                        <span>Orgao expedidor</span>
                        <input type="text" name="representante_orgao_expedidor" value="<?= h($familia['representante_orgao_expedidor'] ?? '') ?>" maxlength="30" required data-representative-input>
                        <?php if (!empty($errors['representante_orgao_expedidor'])): ?>
                            <small class="field-error"><?= h($errors['representante_orgao_expedidor'][0]) ?></small>
                        <?php endif; ?>
                    </label>
                    <label class="field date-field">
                        <span>Data de nascimento</span>
                        <input type="date" name="representante_data_nascimento" value="<?= h($familia['representante_data_nascimento'] ?? '') ?>" required data-representative-input>
                        <?php if (!empty($errors['representante_data_nascimento'])): ?>
                            <small class="field-error"><?= h($errors['representante_data_nascimento'][0]) ?></small>
                        <?php endif; ?>
                    </label>
                    <label class="field">
                        <span>Sexo</span>
                        <select name="representante_sexo" required data-representative-input>
                            <option value="">Selecione</option>
                            <option value="feminino" <?= $optionSelected($familia, 'representante_sexo', 'feminino') ?>>Feminino</option>
                            <option value="masculino" <?= $optionSelected($familia, 'representante_sexo', 'masculino') ?>>Masculino</option>
                            <option value="outro" <?= $optionSelected($familia, 'representante_sexo', 'outro') ?>>Outro</option>
                            <option value="nao_informado" <?= $optionSelected($familia, 'representante_sexo', 'nao_informado') ?>>Nao informado</option>
                        </select>
                        <?php if (!empty($errors['representante_sexo'])): ?>
                            <small class="field-error"><?= h($errors['representante_sexo'][0]) ?></small>
                        <?php endif; ?>
                    </label>
                </div>

                <label class="field">
                    <span>Telefone</span>
                    <input type="text" name="representante_telefone" value="<?= h($familia['representante_telefone'] ?? '') ?>" maxlength="30" data-representative-input>
                </label>
            </div>
        </section>

        <section class="form-block residence-form-block family-form-block family-conclusion-block">
            <div class="form-block-heading">
                <span>Conclusao</span>
                <strong>Fechamento do cadastro</strong>
            </div>

            <label class="completion-check">
                <input type="checkbox" name="cadastro_concluido" value="1" required <?= !empty($familia['cadastro_concluido']) ? 'checked' : '' ?>>
                <span>Cadastro familiar revisado e concluido</span>
            </label>
            <?php if (!empty($errors['cadastro_concluido'])): ?>
                <small class="field-error"><?= h($errors['cadastro_concluido'][0]) ?></small>
            <?php endif; ?>

            <label class="field">
                <span>Observacoes finais</span>
                <textarea name="conclusao_observacoes" rows="3" maxlength="1000" required><?= h($familia['conclusao_observacoes'] ?? '') ?></textarea>
                <?php if (!empty($errors['conclusao_observacoes'])): ?>
                    <small class="field-error"><?= h($errors['conclusao_observacoes'][0]) ?></small>
                <?php endif; ?>
            </label>
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
