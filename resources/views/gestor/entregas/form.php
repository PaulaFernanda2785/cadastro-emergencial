<section class="form-shell">
    <div class="section-heading">
        <span class="eyebrow">Entrega de ajuda</span>
        <h1><?= h($title) ?></h1>
        <p><?= h($familia['responsavel_nome']) ?> - residencia <?= h($familia['protocolo']) ?></p>
    </div>

    <section class="detail-grid">
        <article class="detail-panel">
            <h2>Familia</h2>
            <p><?= h($familia['responsavel_nome']) ?></p>
            <p>CPF: <?= h($familia['responsavel_cpf']) ?></p>
        </article>
        <article class="detail-panel">
            <h2>Localizacao</h2>
            <p><?= h($familia['bairro_comunidade']) ?> - <?= h($familia['municipio_nome']) ?>/<?= h($familia['uf']) ?></p>
            <p><?= h($familia['endereco']) ?></p>
        </article>
        <article class="detail-panel">
            <h2>Acao</h2>
            <p><?= h($familia['localidade']) ?></p>
            <p><?= h($familia['tipo_evento']) ?></p>
        </article>
    </section>

    <form method="post" action="<?= h(url($action)) ?>" class="form panel-form js-prevent-double-submit" novalidate>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <label class="field">
            <span>Tipo de ajuda</span>
            <select name="tipo_ajuda_id" required>
                <option value="">Selecione</option>
                <?php foreach ($tipos as $tipo): ?>
                    <option value="<?= h($tipo['id']) ?>" <?= (string) ($entrega['tipo_ajuda_id'] ?? '') === (string) $tipo['id'] ? 'selected' : '' ?>>
                        <?= h($tipo['nome']) ?> (<?= h($tipo['unidade_medida']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['tipo_ajuda_id'])): ?>
                <small class="field-error"><?= h($errors['tipo_ajuda_id'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Quantidade</span>
            <input type="number" name="quantidade" value="<?= h($entrega['quantidade'] ?? '1') ?>" min="0.01" step="0.01" required>
            <?php if (!empty($errors['quantidade'])): ?>
                <small class="field-error"><?= h($errors['quantidade'][0]) ?></small>
            <?php endif; ?>
        </label>

        <label class="field field-full">
            <span>Observacao</span>
            <textarea name="observacao" maxlength="500" rows="4"><?= h($entrega['observacao'] ?? '') ?></textarea>
            <?php if (!empty($errors['observacao'])): ?>
                <small class="field-error"><?= h($errors['observacao'][0]) ?></small>
            <?php endif; ?>
        </label>

        <?php if ($tipos === []): ?>
            <div class="alert alert-warning" role="alert">Cadastre e ative pelo menos um tipo de ajuda antes de registrar entregas.</div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Registrando..." <?= $tipos === [] ? 'disabled' : '' ?>>
                <span class="button-label">Registrar entrega</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
            <a class="secondary-link" href="<?= h(url('/cadastros/residencias/' . $familia['residencia_id'])) ?>">Cancelar</a>
        </div>
    </form>
</section>

<section class="table-panel">
    <div class="table-heading">
        <h2>Historico da familia</h2>
        <span><?= h(count($historico)) ?> entrega(s)</span>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Comprovante</th>
                <th>Ajuda</th>
                <th>Quantidade</th>
                <th>Data</th>
                <th>Responsavel</th>
                <th class="actions-column">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($historico as $item): ?>
                <tr>
                    <td><?= h($item['comprovante_codigo']) ?></td>
                    <td><?= h($item['tipo_ajuda_nome']) ?></td>
                    <td><?= h(number_format((float) $item['quantidade'], 2, ',', '.')) ?> <?= h($item['unidade_medida']) ?></td>
                    <td><?= h(date('d/m/Y H:i', strtotime((string) $item['data_entrega']))) ?></td>
                    <td><?= h($item['entregue_por_nome']) ?></td>
                    <td class="actions-column">
                        <a href="<?= h(url('/gestor/entregas/' . $item['id'] . '/comprovante')) ?>">Comprovante</a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($historico === []): ?>
                <tr>
                    <td colspan="6" class="empty-state">Nenhuma entrega anterior para esta familia.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
