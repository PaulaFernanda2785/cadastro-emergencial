<section class="form-shell">
    <div class="section-heading">
        <span class="eyebrow">Conta</span>
        <h1>Alterar senha</h1>
        <p>Atualize sua senha de acesso ao sistema.</p>
    </div>

    <form method="post" action="<?= h(url('/alterar-senha')) ?>" class="form panel-form js-prevent-double-submit" novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field('auth.change_password') ?>

        <label class="field">
            <span>Senha atual</span>
            <input type="password" name="senha_atual" required autocomplete="current-password">
            <?php if (!empty($errors['senha_atual'])): ?>
                <small class="field-error"><?= h($errors['senha_atual'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Nova senha</span>
            <input type="password" name="nova_senha" minlength="8" required autocomplete="new-password">
            <?php if (!empty($errors['nova_senha'])): ?>
                <small class="field-error"><?= h($errors['nova_senha'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Confirmar nova senha</span>
            <input type="password" name="confirmar_senha" minlength="8" required autocomplete="new-password">
            <?php if (!empty($errors['confirmar_senha'])): ?>
                <small class="field-error"><?= h($errors['confirmar_senha'][0]) ?></small>
            <?php endif; ?>
        </label>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Alterando...">
                <span class="button-label">Alterar senha</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/dashboard')) ?>">Cancelar</a>
        </div>
    </form>
</section>
