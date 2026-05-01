<section class="auth-shell qr-register-shell">
    <div class="auth-panel auth-register-panel qr-register-panel">
        <div class="auth-brand-header qr-register-brand">
            <img src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA" class="auth-brand-logo">
            <div class="auth-brand-title">
                <strong>Cadastro Emergencial</strong>
                <span>Aplicativo de campo</span>
            </div>
        </div>

        <div class="qr-register-heading">
            <span class="eyebrow">Cadastro via QR Code</span>
            <h1>Criar acesso</h1>
            <?php if (!empty($acao)): ?>
                <div class="qr-action-summary">
                    <span>Acao vinculada</span>
                    <strong><?= h($acao['municipio_nome']) ?>/<?= h($acao['uf']) ?> - <?= h($acao['localidade']) ?></strong>
                    <small><?= h($acao['tipo_evento']) ?></small>
                </div>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= h(url('/cadastro-qr')) ?>" class="form qr-register-form js-prevent-double-submit" novalidate>
            <?= csrf_field() ?>
            <?= idempotency_field('auth.qr_register') ?>

            <section class="qr-register-block">
                <div class="qr-register-block-heading">
                    <h2>Identificacao</h2>
                </div>

                <label class="field">
                    <span>Nome completo</span>
                    <input type="text" name="nome" value="<?= h($usuario['nome'] ?? '') ?>" maxlength="180" autocomplete="name" required autofocus>
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
                        <span>Telefone</span>
                        <input type="text" name="telefone" value="<?= h($usuario['telefone'] ?? '') ?>" maxlength="30" autocomplete="tel">
                        <?php if (!empty($errors['telefone'])): ?>
                            <small class="field-error"><?= h($errors['telefone'][0]) ?></small>
                        <?php endif; ?>
                    </label>
                </div>
            </section>

            <section class="qr-register-block">
                <div class="qr-register-block-heading">
                    <h2>Secao militar</h2>
                    <span class="access-profile-badge">Opcional</span>
                </div>

                <div class="military-toggle">
                    <div>
                        <span>Usuario militar</span>
                        <strong>Graduacao e nome de guerra</strong>
                    </div>
                    <label class="switch-control" aria-label="Usuario militar">
                        <input type="checkbox" name="militar" value="1" data-military-toggle <?= !empty($usuario['militar']) ? 'checked' : '' ?>>
                    </label>
                </div>

                <div class="form-grid two-columns military-fields" data-military-fields <?= !empty($usuario['militar']) ? '' : 'hidden' ?>>
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
                </div>
            </section>

            <section class="qr-register-block">
                <div class="qr-register-block-heading">
                    <h2>Conta e acesso</h2>
                    <span class="access-profile-badge">Perfil cadastrador</span>
                </div>

                <label class="field">
                    <span>E-mail</span>
                    <input type="email" name="email" value="<?= h($usuario['email'] ?? '') ?>" maxlength="180" autocomplete="username" required>
                    <?php if (!empty($errors['email'])): ?>
                        <small class="field-error"><?= h($errors['email'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <div class="form-grid two-columns">
                    <label class="field">
                        <span>Senha</span>
                        <input type="password" name="senha" minlength="8" autocomplete="new-password" required>
                        <?php if (!empty($errors['senha'])): ?>
                            <small class="field-error"><?= h($errors['senha'][0]) ?></small>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>Confirmar senha</span>
                        <input type="password" name="confirmar_senha" minlength="8" autocomplete="new-password" required>
                        <?php if (!empty($errors['confirmar_senha'])): ?>
                            <small class="field-error"><?= h($errors['confirmar_senha'][0]) ?></small>
                        <?php endif; ?>
                    </label>
                </div>
            </section>

            <section class="qr-register-block">
                <div class="qr-register-block-heading">
                    <h2>Instituicao</h2>
                </div>

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
            </section>

            <button type="submit" class="primary-button auth-submit-button" data-loading-text="Criando cadastro...">
                <span class="button-label">Criar cadastro e iniciar</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
        </form>

        <div class="auth-secondary-action">
            <span>Ja possui cadastro?</span>
            <a class="secondary-button auth-secondary-button" href="<?= h(url('/login')) ?>">Entrar</a>
        </div>
    </div>
</section>
