<section class="auth-shell">
    <div class="auth-panel auth-register-panel">
        <div class="auth-brand-header">
            <img src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA" class="auth-brand-logo">
            <div class="auth-brand-title">
                <strong>Cadastro Emergencial</strong>
                <span>Aplicativo de campo</span>
            </div>
        </div>

        <div class="section-heading">
            <span class="eyebrow">Cadastro pelo QR Code</span>
            <h1>Criar acesso de cadastrador</h1>
            <p>Informe seus dados para acessar o aplicativo de cadastro da ação emergencial.</p>
        </div>

        <form method="post" action="<?= h(url('/cadastro-qr')) ?>" class="form js-prevent-double-submit" novalidate>
            <?= csrf_field() ?>
            <?= idempotency_field('auth.qr_register') ?>

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
                    <input type="text" name="cpf" value="<?= h($usuario['cpf'] ?? '') ?>" maxlength="14" inputmode="numeric" required>
                    <?php if (!empty($errors['cpf'])): ?>
                        <small class="field-error"><?= h($errors['cpf'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <label class="field">
                    <span>E-mail</span>
                    <input type="email" name="email" value="<?= h($usuario['email'] ?? '') ?>" maxlength="180" autocomplete="email" required>
                    <?php if (!empty($errors['email'])): ?>
                        <small class="field-error"><?= h($errors['email'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <label class="field">
                <span>Telefone</span>
                <input type="text" name="telefone" value="<?= h($usuario['telefone'] ?? '') ?>" maxlength="30" autocomplete="tel">
                <?php if (!empty($errors['telefone'])): ?>
                    <small class="field-error"><?= h($errors['telefone'][0]) ?></small>
                <?php endif; ?>
            </label>

            <div class="form-grid two-columns">
                <label class="field">
                    <span>Órgão/instituição</span>
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

            <div class="readonly-field">
                <span>Perfil criado</span>
                <strong>Cadastrador</strong>
            </div>

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

            <button type="submit" class="primary-button auth-submit-button" data-loading-text="Criando cadastro...">
                <span class="button-label">Criar cadastro</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
        </form>

        <div class="auth-secondary-action">
            <span>Já possui cadastro?</span>
            <a class="secondary-button auth-secondary-button" href="<?= h(url('/login')) ?>">Entrar</a>
        </div>
    </div>
</section>
