<section class="action-form-page account-password-page">
    <header class="action-form-header">
        <div>
            <span class="eyebrow">Conta</span>
            <h1>Alterar senha</h1>
            <p>Atualize sua senha de acesso usando a senha atual para confirmar a operacao.</p>
        </div>
        <a class="secondary-button action-back-link" href="<?= h(url('/dashboard')) ?>">Voltar ao painel</a>
    </header>

    <form method="post" action="<?= h(url('/alterar-senha')) ?>" class="form action-form-card account-password-card js-prevent-double-submit" novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field('auth.change_password') ?>

        <section class="form-block account-password-block">
            <div class="form-block-heading">
                <span>1</span>
                <div>
                    <h2>Confirmacao</h2>
                    <p>Informe sua senha atual antes de definir uma nova senha.</p>
                </div>
            </div>

            <label class="field styled-field account-password-current">
                <span>Senha atual</span>
                <input type="password" name="senha_atual" required autocomplete="current-password" autofocus>
                <?php if (!empty($errors['senha_atual'])): ?>
                    <small class="field-error"><?= h($errors['senha_atual'][0]) ?></small>
                <?php endif; ?>
            </label>
        </section>

        <section class="form-block account-password-block">
            <div class="form-block-heading">
                <span>2</span>
                <div>
                    <h2>Nova senha</h2>
                    <p>A nova senha deve ter no minimo 8 caracteres.</p>
                </div>
            </div>

            <div class="form-grid two-columns">
                <label class="field styled-field">
                    <span>Nova senha</span>
                    <input type="password" name="nova_senha" minlength="8" required autocomplete="new-password">
                    <?php if (!empty($errors['nova_senha'])): ?>
                        <small class="field-error"><?= h($errors['nova_senha'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <label class="field styled-field">
                    <span>Confirmar nova senha</span>
                    <input type="password" name="confirmar_senha" minlength="8" required autocomplete="new-password">
                    <?php if (!empty($errors['confirmar_senha'])): ?>
                        <small class="field-error"><?= h($errors['confirmar_senha'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>
        </section>

        <div class="form-actions action-form-actions">
            <button type="submit" class="primary-button" data-loading-text="Alterando...">
                <span class="button-label">Alterar senha</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/dashboard')) ?>">Cancelar e voltar</a>
        </div>
    </form>
</section>
