<section class="auth-shell">
    <div class="auth-panel">
        <div class="auth-brand-header">
            <img src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA" class="auth-brand-logo">
            <div class="auth-brand-title">
                <strong>Cadastro Emergencial</strong>
                <span>Sistema web</span>
            </div>
        </div>

        <div class="section-heading">
            <span class="eyebrow">Acesso restrito</span>
            <h1>Entrar no sistema</h1>
            <p>Entre com sua conta institucional para operar os cadastros, entregas e relatórios.</p>
        </div>

        <form method="post" action="<?= h(url('/login')) ?>" class="form js-prevent-double-submit" novalidate>
            <?= csrf_field() ?>

            <label class="field">
                <span>E-mail</span>
                <input
                    type="email"
                    name="email"
                    value="<?= h($email ?? '') ?>"
                    maxlength="180"
                    autocomplete="username"
                    required
                    autofocus
                >
                <?php if (!empty($errors['email'])): ?>
                    <small class="field-error"><?= h($errors['email'][0]) ?></small>
                <?php endif; ?>
            </label>

            <label class="field">
                <span>Senha</span>
                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
                <?php if (!empty($errors['password'])): ?>
                    <small class="field-error"><?= h($errors['password'][0]) ?></small>
                <?php endif; ?>
            </label>

            <button type="submit" class="primary-button auth-submit-button" data-loading-text="Processando...">
                <span class="button-label">Entrar</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
        </form>

        <?php if (!empty($canRegister)): ?>
            <div class="auth-secondary-action">
                <span>Vai cadastrar pelo QR Code?</span>
                <a class="secondary-button auth-secondary-button" href="<?= h(url('/cadastro-qr')) ?>">Não tenho cadastro</a>
            </div>
        <?php endif; ?>
    </div>
</section>
