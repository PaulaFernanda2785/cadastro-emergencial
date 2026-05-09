<?php
$batchFilters = $batchFilters ?? [];
$batchHasFilters = !empty($batchHasFilters);
$deliveryMode = ($deliveryMode ?? 'entregar') === 'registrar' ? 'registrar' : 'entregar';
$isRegisterMode = $deliveryMode === 'registrar';
$formAction = $formAction ?? ($isRegisterMode ? '/gestor/entregas/registrar/lote' : '/gestor/entregas/lote');
$filterAction = $isRegisterMode ? '/gestor/entregas/registrar' : '/gestor/entregas/lote';
$idempotencyScope = $isRegisterMode ? 'gestor.entregas.registrar.lote' : 'gestor.entregas.lote';
$submitText = $isRegisterMode ? 'Registrar itens em lote' : 'Confirmar entrega em lote';
$loadingText = $isRegisterMode ? 'Registrando lote...' : 'Confirmando entrega...';
$acoesAbertas = array_values(array_filter($acoes ?? [], static fn (array $acao): bool => (string) ($acao['status'] ?? '') === 'aberta'));
$acaoSelecionada = '';
$residenciaSelecionada = '';
$actionOptionLabel = static function (array $acao): string {
    return trim(
        (string) ($acao['municipio_nome'] ?? '') . '/' . (string) ($acao['uf'] ?? '')
        . ' - ' . (string) ($acao['localidade'] ?? '')
        . ' - ' . (string) ($acao['tipo_evento'] ?? '')
        . ' - Acao #' . (string) ($acao['id'] ?? '')
    );
};

foreach ($acoesAbertas as $acao) {
    if ((string) ($batchFilters['acao_id'] ?? '') === (string) $acao['id']) {
        $acaoSelecionada = $actionOptionLabel($acao);
        break;
    }
}

foreach ($residencias ?? [] as $residencia) {
    if ((string) ($batchFilters['residencia_id'] ?? '') === (string) $residencia['id']) {
        $residenciaSelecionada = $residencia['protocolo'] . ' - ' . $residencia['bairro_comunidade'];
        break;
    }
}

require BASE_PATH . '/resources/views/gestor/entregas/_nav.php';
?>

<section class="dashboard-header deliveries-header">
    <div>
        <span class="eyebrow"><?= $isRegisterMode ? 'Registro em lote' : 'Entrega em lote' ?></span>
        <h1><?= $isRegisterMode ? 'Registrar itens para familias' : 'Confirmar entrega registrada' ?></h1>
        <p><?= $isRegisterMode ? 'Filtre familias e registre os itens previstos antes da assinatura e da entrega.' : 'Confirme a entrega somente para familias que ja possuem itens registrados.' ?></p>
    </div>
</section>

<?php if ($isRegisterMode): ?>
<section class="delivery-qr-panel no-print" data-delivery-qr-scanner data-validate-base="<?= h(url('/gestor/entregas/registrar/validar')) ?>">
    <div class="delivery-qr-heading">
        <div>
            <span class="eyebrow">Registro individual</span>
            <h2>Registrar por QR Code</h2>
        </div>
        <div class="delivery-qr-actions">
            <button type="button" class="primary-button" data-delivery-qr-start>Ler QR Code</button>
            <button type="button" class="secondary-button" data-delivery-qr-stop hidden>Parar leitura</button>
        </div>
    </div>
    <video class="delivery-qr-video" data-delivery-qr-video muted playsinline hidden></video>
    <p class="delivery-qr-status" data-delivery-qr-status>Leia o QR Code impresso no comprovante ou informe o codigo manualmente.</p>
    <form method="get" action="<?= h(url('/gestor/entregas/registrar/validar')) ?>" class="delivery-qr-manual-form">
        <label class="field">
            <span>Codigo do comprovante</span>
            <input type="text" name="codigo" placeholder="FAM-000000-XXXXXXXXXX" maxlength="26" autocomplete="off">
        </label>
        <button type="submit" class="secondary-button">Registrar por codigo</button>
    </form>
</section>
<?php endif; ?>

<section class="delivery-batch-panel delivery-batch-filter-panel">
    <div class="table-heading">
        <div>
            <span class="eyebrow">Familias elegiveis</span>
            <h2><?= $isRegisterMode ? 'Selecionar familias para registrar' : 'Selecionar familias registradas' ?></h2>
        </div>
        <span><?= h(count($batchFamilies ?? [])) ?> familia(s) disponivel(is)</span>
    </div>

    <form method="get" action="<?= h(url($filterAction)) ?>" class="delivery-batch-filter-form">
        <div class="delivery-batch-filter-grid">
            <label class="field styled-field smart-search-field delivery-batch-filter-field delivery-batch-filter-field-wide">
                <span>Acao aberta</span>
                <input type="search" name="lote_acao_busca" value="<?= h($batchFilters['acao_busca'] ?: $acaoSelecionada) ?>" list="acoes-abertas-list" placeholder="Digite para buscar a acao" data-smart-search data-smart-target="lote_acao_id" autocomplete="off">
                <input type="hidden" name="lote_acao_id" value="<?= h($batchFilters['acao_id'] ?? '') ?>" data-smart-hidden="lote_acao_id">
                <datalist id="acoes-abertas-list">
                    <?php foreach ($acoesAbertas as $acao): ?>
                        <option value="<?= h($actionOptionLabel($acao)) ?>" data-id="<?= h($acao['id']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>
            <label class="field styled-field smart-search-field delivery-batch-filter-field delivery-batch-filter-field-wide">
                <span>Residencia</span>
                <input type="search" name="lote_residencia_busca" value="<?= h($batchFilters['residencia_busca'] ?: $residenciaSelecionada) ?>" list="residencias-lote-list" placeholder="Digite protocolo ou bairro" data-smart-search data-smart-target="lote_residencia_id" autocomplete="off">
                <input type="hidden" name="lote_residencia_id" value="<?= h($batchFilters['residencia_id'] ?? '') ?>" data-smart-hidden="lote_residencia_id">
                <datalist id="residencias-lote-list">
                    <?php foreach ($residencias ?? [] as $residencia): ?>
                        <option value="<?= h($residencia['protocolo'] . ' - ' . $residencia['bairro_comunidade']) ?>" data-id="<?= h($residencia['id']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>
            <label class="field styled-field delivery-batch-filter-field">
                <span>Buscar familia</span>
                <input type="search" name="lote_q" value="<?= h($batchFilters['q'] ?? '') ?>" placeholder="Nome, CPF, residencia">
            </label>
            <label class="field styled-field delivery-batch-filter-field delivery-batch-filter-field-compact">
                <span>Status</span>
                <select name="lote_status_entrega" <?= $isRegisterMode ? '' : 'disabled' ?>>
                    <option value="">Todos</option>
                    <option value="registrado" <?= ($batchFilters['status_entrega'] ?? '') === 'registrado' ? 'selected' : '' ?>>Registrado</option>
                    <option value="entregue" <?= ($batchFilters['status_entrega'] ?? '') === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                    <option value="nao_entregue" <?= ($batchFilters['status_entrega'] ?? '') === 'nao_entregue' ? 'selected' : '' ?>>Sem registro</option>
                </select>
                <?php if (!$isRegisterMode): ?>
                    <input type="hidden" name="lote_status_entrega" value="registrado">
                <?php endif; ?>
            </label>
            <label class="field styled-field delivery-batch-filter-field delivery-batch-filter-field-date">
                <span>Inicio cadastro</span>
                <input type="date" name="lote_data_inicio" value="<?= h($batchFilters['data_inicio'] ?? '') ?>">
            </label>
            <label class="field styled-field delivery-batch-filter-field delivery-batch-filter-field-date">
                <span>Fim cadastro</span>
                <input type="date" name="lote_data_fim" value="<?= h($batchFilters['data_fim'] ?? '') ?>">
            </label>
        </div>
        <div class="delivery-batch-filter-actions">
            <button type="submit" class="primary-button">Carregar familias</button>
            <a class="secondary-button" href="<?= h(url($filterAction)) ?>">Limpar</a>
        </div>
    </form>
</section>

<section class="delivery-batch-panel">
    <form method="post" action="<?= h(url($formAction)) ?>" class="delivery-batch-form js-prevent-double-submit" data-delivery-batch-form <?= $isRegisterMode ? 'data-delivery-items-form' : '' ?>>
        <?= csrf_field() ?>
        <?= idempotency_field($idempotencyScope) ?>

        <?php if ($batchHasFilters): ?>
            <div class="delivery-batch-toolbar">
                <button type="button" class="secondary-button" data-batch-select-all>Selecionar todos</button>
                <button type="button" class="secondary-button" data-batch-clear>Desmarcar todos</button>
                <span data-batch-counter>0 selecionada(s)</span>
            </div>
        <?php endif; ?>

        <?php if ($isRegisterMode): ?>
            <div class="delivery-type-grid">
                <?php foreach ($tipos ?? [] as $tipo): ?>
                    <?php $tipoId = (int) $tipo['id']; ?>
                    <div class="delivery-type-option delivery-type-option-with-fields" data-delivery-type-option>
                        <label class="delivery-type-check">
                            <input type="checkbox" name="tipo_ajuda_ids[]" value="<?= h($tipoId) ?>" data-delivery-type-toggle>
                            <span>
                                <strong><?= h($tipo['nome']) ?></strong>
                                <small><?= h($tipo['unidade_medida']) ?></small>
                            </span>
                        </label>
                        <div class="delivery-type-item-fields" data-delivery-type-fields hidden>
                            <label class="field">
                                <span>Quantidade</span>
                                <input type="number" name="itens[<?= h($tipoId) ?>][quantidade]" value="1" min="0.01" step="0.01" disabled data-delivery-type-input>
                            </label>
                            <label class="field">
                                <span>Observacao do item</span>
                                <input type="text" name="itens[<?= h($tipoId) ?>][observacao]" maxlength="500" placeholder="Opcional" disabled data-delivery-type-input>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($batchHasFilters): ?>
            <div class="delivery-family-list">
                <?php foreach ($batchFamilies ?? [] as $familia): ?>
                    <?php
                    $pendingCount = (int) ($familia['registros_pendentes'] ?? 0);
                    $deliveredCount = (int) ($familia['entregas_registradas'] ?? 0);
                    $statusText = $pendingCount > 0 ? 'Registrado' : ($deliveredCount > 0 ? 'Entregue' : 'Sem registro');
                    $itemsText = $pendingCount > 0 ? ($familia['registros_itens_resumo'] ?? '') : ($familia['entregas_itens_resumo'] ?? '');
                    ?>
                    <label class="delivery-family-card <?= $pendingCount > 0 ? 'is-pending' : ($deliveredCount > 0 ? 'is-delivered' : 'is-clear') ?>">
                        <input type="checkbox" name="familia_ids[]" value="<?= h($familia['id']) ?>" data-batch-family <?= !$isRegisterMode && $pendingCount <= 0 ? 'disabled' : '' ?>>
                        <span>
                            <strong><?= h($familia['responsavel_nome']) ?></strong>
                            <small><?= h($familia['responsavel_cpf']) ?> - <?= h($familia['protocolo']) ?></small>
                            <small><?= h($familia['bairro_comunidade']) ?>, <?= h($familia['municipio_nome']) ?>/<?= h($familia['uf']) ?></small>
                            <?php if ($itemsText !== ''): ?>
                                <small class="delivery-family-items"><?= h($itemsText) ?></small>
                            <?php endif; ?>
                            <?php if ($deliveredCount > 0): ?>
                                <small>Ultima entrega: <?= !empty($familia['ultima_entrega']) ? h(date('d/m/Y H:i', strtotime((string) $familia['ultima_entrega']))) : '-' ?></small>
                            <?php endif; ?>
                        </span>
                        <em class="delivery-family-status"><?= h($statusText) ?></em>
                    </label>
                <?php endforeach; ?>

                <?php if (($batchFamilies ?? []) === []): ?>
                    <div class="empty-state">Nenhuma familia encontrada para os filtros do lote.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="delivery-batch-empty">
                Use os filtros acima e clique em Carregar familias para exibir a lista.
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="<?= h($loadingText) ?>" <?= (!$batchHasFilters || ($batchFamilies ?? []) === []) ? 'disabled' : '' ?>>
                <span class="button-label"><?= h($submitText) ?></span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
        </div>
    </form>
</section>
