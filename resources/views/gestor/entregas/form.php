<?php
$selectedTipos = array_map('strval', $entrega['tipo_ajuda_ids'] ?? []);
$historico = $historico ?? [];
$historicoRegistrado = array_values(array_filter($historico, static fn (array $item): bool => (string) ($item['status_operacional'] ?? 'entregue') === 'registrado'));
$historicoEntregue = array_values(array_filter($historico, static fn (array $item): bool => (string) ($item['status_operacional'] ?? 'entregue') === 'entregue'));
$hasRegisteredItems = $historicoRegistrado !== [];
$hasDeliveredItems = $historicoEntregue !== [];
$hasPreviousDeliveries = $hasRegisteredItems || $hasDeliveredItems;
$recentHistory = array_slice($historico, 0, 4);
$itemInput = static function (array $entrega, mixed $tipoId, string $field, string $default = ''): string {
    $tipoId = (int) $tipoId;
    $items = is_array($entrega['itens'] ?? null) ? $entrega['itens'] : [];

    return (string) ($items[$tipoId][$field] ?? $default);
};
?>
<section class="delivery-form-page">
    <section class="dashboard-header">
        <div>
            <span class="eyebrow">Registro de itens</span>
            <h1><?= h($title) ?></h1>
            <p><?= h($familia['responsavel_nome']) ?> - residência <?= h($familia['protocolo']) ?></p>
        </div>
        <div class="header-actions">
            <a class="secondary-button" href="<?= h(url('/gestor/entregas')) ?>">Voltar para entregas</a>
            <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $familia['residencia_id'])) ?>">Ver residência</a>
        </div>
    </section>

    <section class="detail-grid delivery-detail-grid">
        <article class="detail-panel">
            <h2>Família</h2>
            <p><?= h($familia['responsavel_nome']) ?></p>
            <p>CPF: <?= h($familia['responsavel_cpf']) ?></p>
            <p>Integrantes: <?= h($familia['quantidade_integrantes']) ?></p>
        </article>
        <article class="detail-panel">
            <h2>Localização</h2>
            <p><?= h($familia['bairro_comunidade']) ?> - <?= h($familia['municipio_nome']) ?>/<?= h($familia['uf']) ?></p>
            <p><?= h($familia['endereco']) ?></p>
        </article>
        <article class="detail-panel">
            <h2>Ação</h2>
            <p><?= h($familia['localidade']) ?></p>
            <p><?= h($familia['tipo_evento']) ?></p>
        </article>
    </section>

    <section class="delivery-family-alert <?= $hasRegisteredItems ? 'is-pending' : ($hasDeliveredItems ? 'is-delivered' : 'is-clear') ?>">
        <div class="delivery-family-alert-heading">
            <div>
                <span class="eyebrow">Situacao operacional</span>
                <h2>
                    <?php if ($hasRegisteredItems): ?>
                        Familia com itens registrados
                    <?php elseif ($hasDeliveredItems): ?>
                        Familia ja teve entrega confirmada
                    <?php else: ?>
                        Sem registro anterior
                    <?php endif; ?>
                </h2>
                <p>
                    <?php if ($hasRegisteredItems): ?>
                        Existem itens registrados pendentes. A entrega so fica disponivel apos esse registro.
                    <?php elseif ($hasDeliveredItems): ?>
                        Confira os itens ja entregues antes de registrar novos itens para esta familia.
                    <?php else: ?>
                        Nenhum registro ou entrega foi localizado para esta familia.
                    <?php endif; ?>
                </p>
            </div>
            <strong class="delivery-family-alert-status">
                <?= $hasPreviousDeliveries ? h(count($historicoRegistrado) . ' registrado(s) / ' . count($historicoEntregue) . ' entregue(s)') : 'Sem registro' ?>
            </strong>
        </div>

        <?php if ($hasPreviousDeliveries): ?>
            <div class="delivery-family-alert-list">
                <?php foreach ($recentHistory as $item): ?>
                    <?php
                    $itemStatus = (string) ($item['status_operacional'] ?? 'entregue');
                    $itemDate = $itemStatus === 'registrado'
                        ? (string) ($item['registrado_em'] ?? $item['data_entrega'] ?? '')
                        : (string) ($item['entregue_em'] ?? $item['data_entrega'] ?? '');
                    ?>
                    <article>
                        <strong><?= h($item['itens_resumo'] ?? $item['tipo_ajuda_nome'] ?? '-') ?></strong>
                        <span>
                            <?= h(strtotime($itemDate) !== false ? date('d/m/Y H:i', strtotime($itemDate)) : '-') ?>
                            - <?= $itemStatus === 'registrado' ? 'registro' : 'entrega' ?> <?= h($item['comprovante_codigo']) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if (count($historico) > count($recentHistory)): ?>
                <small class="delivery-family-alert-more">Mais <?= h(count($historico) - count($recentHistory)) ?> comprovante(s) no histórico abaixo.</small>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= h(url($action)) ?>" class="delivery-entry-panel js-prevent-double-submit" novalidate data-delivery-items-form>
        <?= csrf_field() ?>
        <?= idempotency_field($action) ?>

        <div class="table-heading">
            <div>
                <span class="eyebrow">Itens para prestacao</span>
                <h2>Selecione um ou mais tipos de ajuda</h2>
            </div>
            <span><?= h(count($tipos)) ?> tipo(s) ativo(s)</span>
        </div>

        <?php if (!empty($errors['tipo_ajuda_ids'])): ?>
            <div class="alert alert-error" role="alert"><?= h($errors['tipo_ajuda_ids'][0]) ?></div>
        <?php endif; ?>

        <div class="delivery-type-grid">
            <?php foreach ($tipos as $tipo): ?>
                <?php
                $tipoId = (int) $tipo['id'];
                $isSelected = in_array((string) $tipoId, $selectedTipos, true);
                ?>
                <div class="delivery-type-option delivery-type-option-with-fields" data-delivery-type-option>
                    <label class="delivery-type-check">
                        <input type="checkbox" name="tipo_ajuda_ids[]" value="<?= h($tipoId) ?>" <?= $isSelected ? 'checked' : '' ?> data-delivery-type-toggle>
                        <span>
                            <strong><?= h($tipo['nome']) ?></strong>
                            <small><?= h($tipo['unidade_medida']) ?></small>
                        </span>
                    </label>
                    <div class="delivery-type-item-fields" data-delivery-type-fields <?= $isSelected ? '' : 'hidden' ?>>
                        <label class="field">
                            <span>Quantidade</span>
                            <input type="number" name="itens[<?= h($tipoId) ?>][quantidade]" value="<?= h($itemInput($entrega, $tipoId, 'quantidade', '1')) ?>" min="0.01" step="0.01" data-delivery-type-input <?= $isSelected ? '' : 'disabled' ?>>
                            <?php if (!empty($errors['item_quantidade_' . $tipoId])): ?>
                                <small class="field-error"><?= h($errors['item_quantidade_' . $tipoId][0]) ?></small>
                            <?php endif; ?>
                        </label>
                        <label class="field">
                            <span>Observação do item</span>
                            <input type="text" name="itens[<?= h($tipoId) ?>][observacao]" value="<?= h($itemInput($entrega, $tipoId, 'observacao')) ?>" maxlength="500" placeholder="Opcional" data-delivery-type-input <?= $isSelected ? '' : 'disabled' ?>>
                            <?php if (!empty($errors['item_observacao_' . $tipoId])): ?>
                                <small class="field-error"><?= h($errors['item_observacao_' . $tipoId][0]) ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($tipos === []): ?>
            <div class="alert alert-warning" role="alert">Cadastre e ative pelo menos um tipo de ajuda antes de registrar itens.</div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Registrando..." <?= $tipos === [] ? 'disabled' : '' ?>>
                <span class="button-label">Registrar itens</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
        </div>
    </form>
</section>

<section class="table-panel delivery-table-panel delivery-family-history-panel">
    <div class="table-heading">
        <h2>Histórico da família</h2>
        <span><?= h(count($historicoRegistrado)) ?> registrado(s) / <?= h(count($historicoEntregue)) ?> entregue(s)</span>
    </div>
    <?php if ($historico === []): ?>
        <div class="empty-state">Nenhum registro anterior para esta familia.</div>
    <?php else: ?>
        <div class="delivery-family-history-list">
            <?php foreach ($historico as $item): ?>
                <?php
                $itemStatus = (string) ($item['status_operacional'] ?? 'entregue');
                $itemDate = $itemStatus === 'registrado'
                    ? (string) ($item['registrado_em'] ?? $item['data_entrega'] ?? '')
                    : (string) ($item['entregue_em'] ?? $item['data_entrega'] ?? '');
                ?>
                <article class="delivery-family-history-card">
                    <div class="delivery-family-history-main">
                        <span class="eyebrow">Comprovante</span>
                        <strong><?= h($item['comprovante_codigo']) ?></strong>
                    </div>
                    <div class="delivery-family-history-items">
                        <span class="eyebrow"><?= $itemStatus === 'registrado' ? 'Ajuda registrada' : 'Ajuda entregue' ?></span>
                        <p><?= h($item['itens_resumo'] ?? $item['tipo_ajuda_nome'] ?? '-') ?></p>
                    </div>
                    <div class="delivery-family-history-meta">
                        <span>
                            <small>Quantidade</small>
                            <strong><?= h(number_format((float) ($item['quantidade_total'] ?? $item['quantidade'] ?? 0), 2, ',', '.')) ?></strong>
                        </span>
                        <span>
                            <small>Data</small>
                            <strong><?= h(strtotime($itemDate) !== false ? date('d/m/Y H:i', strtotime($itemDate)) : '-') ?></strong>
                        </span>
                        <span>
                            <small><?= $itemStatus === 'registrado' ? 'Registrado por' : 'Entregue por' ?></small>
                            <strong><?= h($item['entregue_por_nome']) ?></strong>
                        </span>
                    </div>
                    <a class="secondary-button delivery-family-history-action" href="<?= h(url('/gestor/entregas/' . $item['id'] . '/comprovante')) ?>">Comprovante</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
