<section class="action-form-page user-form-page">
    <header class="action-form-header">
        <div>
            <span class="eyebrow">Administração</span>
            <h1>Alterar senha</h1>
            <p><?= h($usuario['nome'] ?? 'Usuário') ?> - <?= h($usuario['email'] ?? '') ?></p>
        </div>
        <a class="secondary-button action-back-link" href="<?= h(url('/admin/usuarios')) ?>">Voltar para usuários</a>
    </header>

    <form method="post" action="<?= h(url($action)) ?>" class="form action-form-card user-form-card js-prevent-double-submit" novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field('admin.usuarios.password.' . ($usuario['id'] ?? '')) ?>

        <section class="form-block user-form-block">
            <div class="form-block-heading">
                <span>1</span>
                <div>
                    <h2>Nova senha</h2>
                    <p>Defina uma nova senha com no mínimo 8 caracteres.</p>
                </div>
            </div>

            <div class="form-grid two-columns">
                <label class="field styled-field">
                    <span>Senha</span>
                    <input type="password" name="senha" minlength="8" autocomplete="new-password" required autofocus>
                    <?php if (!empty($errors['senha'])): ?>
                        <small class="field-error"><?= h($errors['senha'][0]) ?></small>
                    <?php endif; ?>
                </label>

                <label class="field styled-field">
                    <span>Confirmar senha</span>
                    <input type="password" name="confirmar_senha" minlength="8" autocomplete="new-password" required>
                    <?php if (!empty($errors['confirmar_senha'])): ?>
                        <small class="field-error"><?= h($errors['confirmar_senha'][0]) ?></small>
                    <?php endif; ?>
                </label>
            </div>
        </section>

        <div class="form-actions action-form-actions">
            <button type="submit" class="primary-button" data-loading-text="Salvando...">
                <span class="button-label">Salvar senha</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/admin/usuarios')) ?>">Cancelar e voltar</a>
        </div>
    </form>
</section>
