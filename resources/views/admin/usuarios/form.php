<section class="form-shell">
    <div class="section-heading">
        <span class="eyebrow">Administracao</span>
        <h1><?= h($title) ?></h1>
        <p>Defina dados institucionais, perfil de acesso e status da conta.</p>
    </div>

    <form method="post" action="<?= h(url($action)) ?>" class="form panel-form js-prevent-double-submit" novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <label class="field">
            <span>Nome completo</span>
            <input type="text" name="nome" value="<?= h($usuario['nome'] ?? '') ?>" maxlength="180" required>
            <?php if (!empty($errors['nome'])): ?>
                <small class="field-error"><?= h($errors['nome'][0]) ?></small>
            <?php endif; ?>
        </label>

        <div class="form-grid two-columns">
            <label class="field">
                <span>CPF</span>
                <input type="text" name="cpf" value="<?= h($usuario['cpf'] ?? '') ?>" maxlength="14" inputmode="numeric" autocomplete="off" data-cpf-input required>
                <?php if (!empty($errors['cpf'])): ?>
                    <small class="field-error"><?= h($errors['cpf'][0]) ?></small>
                <?php endif; ?>
            </label>

            <label class="field">
                <span>E-mail</span>
                <input type="email" name="email" value="<?= h($usuario['email'] ?? '') ?>" maxlength="180" required>
                <?php if (!empty($errors['email'])): ?>
                    <small class="field-error"><?= h($errors['email'][0]) ?></small>
                <?php endif; ?>
            </label>
        </div>

        <label class="field">
            <span>Telefone</span>
            <input type="text" name="telefone" value="<?= h($usuario['telefone'] ?? '') ?>" maxlength="30">
            <?php if (!empty($errors['telefone'])): ?>
                <small class="field-error"><?= h($errors['telefone'][0]) ?></small>
            <?php endif; ?>
        </label>

        <div class="form-grid two-columns">
            <label class="field">
                <span>Orgao/instituicao</span>
                <input type="text" name="orgao" value="<?= h($usuario['orgao'] ?? '') ?>" maxlength="180">
                <?php if (!empty($errors['orgao'])): ?>
                    <small class="field-error"><?= h($errors['orgao'][0]) ?></small>
                <?php endif; ?>
            </label>

            <label class="field">
                <span>Unidade/setor</span>
                <input type="text" name="unidade_setor" value="<?= h($usuario['unidade_setor'] ?? '') ?>" maxlength="180">
                <?php if (!empty($errors['unidade_setor'])): ?>
                    <small class="field-error"><?= h($errors['unidade_setor'][0]) ?></small>
                <?php endif; ?>
            </label>
        </div>

        <section class="form-block user-military-block">
            <div class="military-toggle">
                <div>
                    <span>Secao militar</span>
                    <strong>Graduacao e nome de guerra</strong>
                </div>
                <label class="switch-control" aria-label="Usuario militar">
                    <input type="checkbox" name="militar" value="1" data-military-toggle <?= !empty($usuario['militar']) ? 'checked' : '' ?>>
                </label>
            </div>

            <div class="form-grid three-columns military-fields" data-military-fields <?= !empty($usuario['militar']) ? '' : 'hidden' ?>>
                <label class="field">
                    <span>Graduacao</span>
                    <input type="text" name="graduacao" value="<?= h($usuario['graduacao'] ?? '') ?>" maxlength="80" data-military-input>
                    <?php if (!empty($errors['graduacao'])): ?>
                        <small class="field-error"><?= h($errors['graduacao'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span>Nome de guerra</span>
                    <input type="text" name="nome_guerra" value="<?= h($usuario['nome_guerra'] ?? '') ?>" maxlength="120" data-military-input>
                    <?php if (!empty($errors['nome_guerra'])): ?>
                        <small class="field-error"><?= h($errors['nome_guerra'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span>Matricula Funcional - MF</span>
                    <input type="text" name="matricula_funcional" value="<?= h($usuario['matricula_funcional'] ?? '') ?>" maxlength="60" data-military-input>
                    <?php if (!empty($errors['matricula_funcional'])): ?>
                        <small class="field-error"><?= h($errors['matricula_funcional'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>
        </section>

        <div class="form-grid two-columns">
            <label class="field">
                <span>Perfil</span>
                <select name="perfil">
                    <?php foreach ($profiles as $profile): ?>
                        <option value="<?= h($profile) ?>" <?= (string) ($usuario['perfil'] ?? '') === $profile ? 'selected' : '' ?>>
                            <?= h(ucfirst($profile)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['perfil'])): ?>
                    <small class="field-error"><?= h($errors['perfil'][0]) ?></small>
                <?php endif; ?>
            </label>

            <label class="field">
                <span>Status</span>
                <select name="ativo">
                    <option value="1" <?= (int) ($usuario['ativo'] ?? 1) === 1 ? 'selected' : '' ?>>Ativo</option>
                    <option value="0" <?= (int) ($usuario['ativo'] ?? 1) === 0 ? 'selected' : '' ?>>Inativo</option>
                </select>
            </label>
        </div>

        <div class="form-grid two-columns">
            <label class="field">
                <span><?= !empty($usuario['id']) ? 'Nova senha' : 'Senha' ?></span>
                <input type="password" name="senha" minlength="8" autocomplete="new-password" <?= empty($usuario['id']) ? 'required' : '' ?>>
                <?php if (!empty($usuario['id'])): ?>
                    <small class="field-hint">Preencha apenas para redefinir.</small>
                <?php endif; ?>
                <?php if (!empty($errors['senha'])): ?>
                    <small class="field-error"><?= h($errors['senha'][0]) ?></small>
                <?php endif; ?>
            </label>

            <label class="field">
                <span>Confirmar senha</span>
                <input type="password" name="confirmar_senha" minlength="8" autocomplete="new-password" <?= empty($usuario['id']) ? 'required' : '' ?>>
                <?php if (!empty($errors['confirmar_senha'])): ?>
                    <small class="field-error"><?= h($errors['confirmar_senha'][0]) ?></small>
                <?php endif; ?>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Salvando...">
                <span class="button-label">Salvar usuario</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/admin/usuarios')) ?>">Cancelar</a>
        </div>
    </form>
</section>
