<section class="form-shell">
    <div class="section-heading">
        <span class="eyebrow">Cadastro de familia</span>
        <h1>Nova familia</h1>
        <p>Residencia <?= h($residencia['protocolo']) ?> - <?= h($residencia['bairro_comunidade']) ?></p>
    </div>

    <form method="post" action="<?= h(url($action)) ?>" class="form panel-form js-prevent-double-submit" novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

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

        <div class="form-grid two-columns">
            <label class="field">
                <span>Data de nascimento</span>
                <input type="date" name="data_nascimento" value="<?= h($familia['data_nascimento'] ?? '') ?>">
                <?php if (!empty($errors['data_nascimento'])): ?>
                    <small class="field-error"><?= h($errors['data_nascimento'][0]) ?></small>
                <?php endif; ?>
            </label>
            <label class="field">
                <span>Quantidade de integrantes</span>
                <input type="number" name="quantidade_integrantes" value="<?= h($familia['quantidade_integrantes'] ?? '1') ?>" min="1" required>
                <?php if (!empty($errors['quantidade_integrantes'])): ?>
                    <small class="field-error"><?= h($errors['quantidade_integrantes'][0]) ?></small>
                <?php endif; ?>
            </label>
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

        <fieldset class="checkbox-panel">
            <legend>Vulnerabilidades</legend>
            <label><input type="checkbox" name="possui_criancas" value="1" <?= !empty($familia['possui_criancas']) ? 'checked' : '' ?>> Criancas</label>
            <label><input type="checkbox" name="possui_idosos" value="1" <?= !empty($familia['possui_idosos']) ? 'checked' : '' ?>> Idosos</label>
            <label><input type="checkbox" name="possui_pcd" value="1" <?= !empty($familia['possui_pcd']) ? 'checked' : '' ?>> Pessoa com deficiencia</label>
        </fieldset>

        <div class="section-heading compact-heading">
            <h2>Representante, se houver</h2>
        </div>

        <label class="field">
            <span>Nome do representante</span>
            <input type="text" name="representante_nome" value="<?= h($familia['representante_nome'] ?? '') ?>" maxlength="180">
        </label>

        <div class="form-grid two-columns">
            <label class="field">
                <span>CPF do representante</span>
                <input type="text" name="representante_cpf" value="<?= h($familia['representante_cpf'] ?? '') ?>" maxlength="14">
            </label>
            <label class="field">
                <span>RG do representante</span>
                <input type="text" name="representante_rg" value="<?= h($familia['representante_rg'] ?? '') ?>" maxlength="30">
            </label>
        </div>

        <label class="field">
            <span>Telefone do representante</span>
            <input type="text" name="representante_telefone" value="<?= h($familia['representante_telefone'] ?? '') ?>" maxlength="30">
        </label>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Processando...">
                <span class="button-label">Salvar familia</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/cadastros/residencias/' . $residencia['id'])) ?>">Cancelar</a>
        </div>
    </form>
</section>
