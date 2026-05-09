<?php require BASE_PATH . '/resources/views/gestor/entregas/_nav.php'; ?>

<section class="delivery-form-page">
    <section class="dashboard-header">
        <div>
            <span class="eyebrow">Entrega registrada</span>
            <h1>Confirmar entrega</h1>
            <p><?= h($familia['responsavel_nome']) ?> - residencia <?= h($familia['protocolo']) ?></p>
        </div>
        <div class="header-actions">
            <a class="secondary-button" href="<?= h(url('/gestor/entregas/validacao')) ?>">Voltar para validar QR</a>
            <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $familia['residencia_id'])) ?>">Ver residencia</a>
        </div>
    </section>

    <section class="detail-grid delivery-detail-grid">
        <article class="detail-panel">
            <h2>Familia</h2>
            <p><?= h($familia['responsavel_nome']) ?></p>
            <p>CPF: <?= h($familia['responsavel_cpf']) ?></p>
            <p>Integrantes: <?= h($familia['quantidade_integrantes']) ?></p>
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

    <section class="table-panel delivery-table-panel delivery-family-history-panel">
        <div class="table-heading">
            <div>
                <span class="eyebrow">Itens registrados</span>
                <h2>Pendentes de entrega</h2>
            </div>
            <span><?= h(count($registros)) ?> registro(s)</span>
        </div>

        <div class="delivery-family-history-list">
            <?php foreach ($registros as $item): ?>
                <article class="delivery-family-history-card">
                    <div class="delivery-family-history-main">
                        <span class="eyebrow">Codigo</span>
                        <strong><?= h($item['comprovante_codigo']) ?></strong>
                    </div>
                    <div class="delivery-family-history-items">
                        <span class="eyebrow">Itens</span>
                        <p><?= h($item['itens_resumo'] ?? '-') ?></p>
                    </div>
                    <div class="delivery-family-history-meta">
                        <span>
                            <small>Quantidade</small>
                            <strong><?= h(number_format((float) ($item['quantidade_total'] ?? 0), 2, ',', '.')) ?></strong>
                        </span>
                        <span>
                            <small>Registrado em</small>
                            <strong><?= !empty($item['registrado_em']) ? h(date('d/m/Y H:i', strtotime((string) $item['registrado_em']))) : '-' ?></strong>
                        </span>
                        <span>
                            <small>Registrado por</small>
                            <strong><?= h($item['entregue_por_nome'] ?? '-') ?></strong>
                        </span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <form method="post" action="<?= h(url($action)) ?>" class="delivery-entry-panel js-prevent-double-submit">
        <?= csrf_field() ?>
        <?= idempotency_field('gestor.entregas.confirmar.' . (int) $familia['id']) ?>
        <div class="table-heading">
            <div>
                <span class="eyebrow">Confirmacao</span>
                <h2>Registrar baixa definitiva</h2>
            </div>
        </div>
        <p>Ao confirmar, todos os itens registrados pendentes desta familia serao marcados como entregues.</p>
        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Confirmando entrega...">
                <span class="button-label">Confirmar entrega</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
        </div>
    </form>
</section>
