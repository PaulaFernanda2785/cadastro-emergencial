<?php
$batchFilters = $batchFilters ?? [];
$batchHasFilters = !empty($batchHasFilters);
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
        <span class="eyebrow">Entrega em lote</span>
        <h1>Baixa coletiva de ajuda humanitaria</h1>
        <p>Filtre familias por acao aberta, residencia ou periodo e escolha quem recebera os tipos de ajuda selecionados.</p>
    </div>
</section>

<section class="delivery-batch-panel">
    <div class="table-heading">
        <div>
            <span class="eyebrow">Familias elegiveis</span>
            <h2>Selecionar familias para entrega</h2>
        </div>
        <span><?= h(count($batchFamilies ?? [])) ?> familia(s) disponivel(is)</span>
    </div>

    <form method="get" action="<?= h(url('/gestor/entregas/lote')) ?>" class="delivery-batch-filter-form">
        <div class="delivery-batch-filter-grid">
            <label class="field smart-search-field">
                <span>Acao aberta</span>
                <input type="search" name="lote_acao_busca" value="<?= h($batchFilters['acao_busca'] ?: $acaoSelecionada) ?>" list="acoes-abertas-list" placeholder="Digite para buscar a acao" data-smart-search data-smart-target="lote_acao_id" autocomplete="off">
                <input type="hidden" name="lote_acao_id" value="<?= h($batchFilters['acao_id'] ?? '') ?>" data-smart-hidden="lote_acao_id">
                <datalist id="acoes-abertas-list">
                    <?php foreach ($acoesAbertas as $acao): ?>
                        <option value="<?= h($actionOptionLabel($acao)) ?>" data-id="<?= h($acao['id']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>
            <label class="field smart-search-field">
                <span>Residencia</span>
                <input type="search" name="lote_residencia_busca" value="<?= h($batchFilters['residencia_busca'] ?: $residenciaSelecionada) ?>" list="residencias-lote-list" placeholder="Digite protocolo ou bairro" data-smart-search data-smart-target="lote_residencia_id" autocomplete="off">
                <input type="hidden" name="lote_residencia_id" value="<?= h($batchFilters['residencia_id'] ?? '') ?>" data-smart-hidden="lote_residencia_id">
                <datalist id="residencias-lote-list">
                    <?php foreach ($residencias ?? [] as $residencia): ?>
                        <option value="<?= h($residencia['protocolo'] . ' - ' . $residencia['bairro_comunidade']) ?>" data-id="<?= h($residencia['id']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>
            <label class="field">
                <span>Buscar familia</span>
                <input type="search" name="lote_q" value="<?= h($batchFilters['q'] ?? '') ?>" placeholder="Nome, CPF, residencia">
            </label>
            <label class="field">
                <span>Status da entrega</span>
                <select name="lote_status_entrega">
                    <option value="">Todos</option>
                    <option value="entregue" <?= ($batchFilters['status_entrega'] ?? '') === 'entregue' ? 'selected' : '' ?>>Com entrega</option>
                    <option value="nao_entregue" <?= ($batchFilters['status_entrega'] ?? '') === 'nao_entregue' ? 'selected' : '' ?>>Sem entrega</option>
                </select>
            </label>
            <label class="field">
                <span>Inicio cadastro</span>
                <input type="date" name="lote_data_inicio" value="<?= h($batchFilters['data_inicio'] ?? '') ?>">
            </label>
            <label class="field">
                <span>Fim cadastro</span>
                <input type="date" name="lote_data_fim" value="<?= h($batchFilters['data_fim'] ?? '') ?>">
            </label>
        </div>
        <div class="delivery-batch-filter-actions">
            <button type="submit" class="primary-button">Carregar familias</button>
            <a class="secondary-button" href="<?= h(url('/gestor/entregas/lote')) ?>">Limpar</a>
        </div>
    </form>
</section>

<section class="delivery-batch-panel">
    <form method="post" action="<?= h(url('/gestor/entregas/lote')) ?>" class="delivery-batch-form js-prevent-double-submit" data-delivery-batch-form>
        <?= csrf_field() ?>
        <?= idempotency_field('gestor.entregas.lote') ?>

        <?php if ($batchHasFilters): ?>
            <div class="delivery-batch-toolbar">
                <button type="button" class="secondary-button" data-batch-select-all>Selecionar todos</button>
                <button type="button" class="secondary-button" data-batch-clear>Desmarcar todos</button>
                <span data-batch-counter>0 selecionada(s)</span>
            </div>
        <?php endif; ?>

        <div class="delivery-type-grid">
            <?php foreach ($tipos ?? [] as $tipo): ?>
                <label class="delivery-type-option">
                    <input type="checkbox" name="tipo_ajuda_ids[]" value="<?= h($tipo['id']) ?>">
                    <span>
                        <strong><?= h($tipo['nome']) ?></strong>
                        <small><?= h($tipo['unidade_medida']) ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="delivery-batch-inputs">
            <label class="field">
                <span>Quantidade por tipo/familia</span>
                <input type="number" name="quantidade" value="1" min="0.01" step="0.01" required>
            </label>
            <label class="field">
                <span>Observacao do lote</span>
                <input type="text" name="observacao" maxlength="500" placeholder="Opcional">
            </label>
        </div>

        <?php if ($batchHasFilters): ?>
            <div class="delivery-family-list">
                <?php foreach ($batchFamilies ?? [] as $familia): ?>
                    <?php $jaEntregue = (int) ($familia['entregas_registradas'] ?? 0) > 0; ?>
                    <label class="delivery-family-card <?= $jaEntregue ? 'is-delivered' : 'is-pending' ?>">
                        <input type="checkbox" name="familia_ids[]" value="<?= h($familia['id']) ?>" data-batch-family>
                        <span>
                            <strong><?= h($familia['responsavel_nome']) ?></strong>
                            <small><?= h($familia['responsavel_cpf']) ?> - <?= h($familia['protocolo']) ?></small>
                            <small><?= h($familia['bairro_comunidade']) ?>, <?= h($familia['municipio_nome']) ?>/<?= h($familia['uf']) ?></small>
                            <?php if ($jaEntregue): ?>
                                <small class="delivery-family-items">Ja entregue: <?= h($familia['entregas_itens_resumo'] ?: 'Entrega registrada') ?></small>
                                <small>Ultima entrega: <?= !empty($familia['ultima_entrega']) ? h(date('d/m/Y H:i', strtotime((string) $familia['ultima_entrega']))) : '-' ?></small>
                            <?php endif; ?>
                        </span>
                        <em class="delivery-family-status"><?= $jaEntregue ? 'Ja entregue' : 'Pendente' ?></em>
                    </label>
                <?php endforeach; ?>

                <?php if (($batchFamilies ?? []) === []): ?>
                    <div class="empty-state">Nenhuma familia encontrada para os filtros do lote.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="delivery-batch-empty">
                Use os filtros acima e clique em Carregar familias para exibir a lista de familias elegiveis.
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="primary-button" data-loading-text="Registrando lote..." <?= (!$batchHasFilters || ($batchFamilies ?? []) === []) ? 'disabled' : '' ?>>
                <span class="button-label">Registrar entrega em lote</span>
                <span class="button-spinner" aria-hidden="true"></span>
            </button>
        </div>
    </form>
</section>
