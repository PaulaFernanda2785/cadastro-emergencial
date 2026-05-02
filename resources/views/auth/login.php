<section class="auth-shell auth-login-page">
    <div class="auth-login-layout">
        <aside class="auth-login-hero" aria-label="Identidade institucional">
            <div class="auth-login-brand">
                <img src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA" class="auth-login-logo">
                <div>
                    <span>CEDEC-PA</span>
                    <strong>Cadastro Emergencial</strong>
                </div>
            </div>

            <div class="auth-login-hero-copy">
                <span class="eyebrow">Operacao integrada</span>
                <h1>Gestao de cadastros, familias e entregas em situacoes emergenciais.</h1>
                <p>Acesso seguro para equipes autorizadas acompanharem a resposta operacional, registros territoriais, documentos e prestacao de contas.</p>
            </div>

            <div class="auth-login-highlights" aria-label="Recursos do sistema">
                <span>Cadastros georreferenciados</span>
                <span>Entregas e relatorios</span>
                <span>Assinaturas digitais</span>
            </div>
        </aside>

        <div class="auth-panel auth-login-panel">
            <div class="auth-brand-header auth-login-panel-header">
                <img src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA" class="auth-brand-logo auth-login-app-logo">
                <div class="auth-brand-title">
                    <strong>Acesso ao sistema</strong>
                    <span>Ambiente restrito e monitorado</span>
                </div>
            </div>

            <div class="section-heading auth-login-heading">
                <span class="eyebrow">Autenticacao</span>
                <h2>Entrar com conta institucional</h2>
                <p>Informe suas credenciais para acessar o painel operacional.</p>
            </div>

            <form method="post" action="<?= h(url('/login')) ?>" class="form auth-login-form js-prevent-double-submit" novalidate>
                <?= csrf_field() ?>

                <label class="field auth-login-field">
                    <span>E-mail</span>
                    <input
                        type="email"
                        name="email"
                        value="<?= h($email ?? '') ?>"
                        maxlength="180"
                        autocomplete="username"
                        placeholder="nome@instituicao.gov.br"
                        required
                        autofocus
                    >
                    <?php if (!empty($errors['email'])): ?>
                        <small class="field-error"><?= h($errors['email'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <label class="field auth-login-field">
                    <span>Senha</span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="Digite sua senha"
                        required
                    >
                    <?php if (!empty($errors['password'])): ?>
                        <small class="field-error"><?= h($errors['password'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <button type="submit" class="primary-button auth-submit-button auth-login-submit" data-loading-text="Processando...">
                    <span class="button-label">Entrar no sistema</span>
                    <span class="button-spinner" aria-hidden="true"></span>
                </button>
            </form>

            <?php if (!empty($canRegister)): ?>
                <div class="auth-secondary-action auth-login-secondary">
                    <span>Vai cadastrar pelo QR Code?</span>
                    <a class="secondary-button auth-secondary-button" href="<?= h(url('/cadastro-qr')) ?>">Nao tenho cadastro</a>
                </div>
            <?php endif; ?>

            <p class="auth-login-footnote">Uso exclusivo de usuarios autorizados. Todas as operacoes podem ser auditadas.</p>
        </div>
    </div>
</section>
