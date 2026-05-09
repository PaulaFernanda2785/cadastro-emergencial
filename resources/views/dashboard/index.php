<?php

$filters = $filters ?? [];
$actions = $actions ?? [];
$indicators = $indicators ?? [];
$conditionBreakdown = $conditionBreakdown ?? [];
$neighborhoodRanking = $neighborhoodRanking ?? [];
$mapResidences = $mapResidences ?? [];
$recentResidences = $recentResidences ?? [];
$scope = $scope ?? [];
$tiposAjudaAtivos = (int) ($tiposAjudaAtivos ?? 0);
$generatedAt = $generatedAt ?? date('d/m/Y H:i');

$selectedActionLabel = '';
foreach ($actions as $action) {
    if ((int) ($action['id'] ?? 0) === (int) ($filters['acao_id'] ?? 0)) {
        $selectedActionLabel = trim((string) ($action['municipio_nome'] ?? '') . '/' . (string) ($action['uf'] ?? '') . ' - ' . (string) ($action['localidade'] ?? '') . ' - ' . (string) ($action['tipo_evento'] ?? '') . ' - ação #' . (string) ($action['id'] ?? ''));
        break;
    }
}

$formatInt = static fn (mixed $value): string => number_format((float) ($value ?? 0), 0, ',', '.');
$percent = static function (mixed $part, mixed $total): string {
    $total = (float) ($total ?? 0);
    if ($total <= 0) {
        return '0%';
    }

    return number_format(((float) ($part ?? 0) / $total) * 100, 1, ',', '.') . '%';
};
$conditionClass = static function (mixed $value): string {
    $key = (string) ($value ?? 'sem_condicao');

    return in_array($key, ['perda_total', 'perda_parcial', 'nao_atingida'], true) ? $key : 'sem_condicao';
};
$conditionLabel = static function (mixed $value): string {
    $key = (string) ($value ?? '');

    return $key !== '' && $key !== 'sem_condicao' ? residencia_condicao_label($key) : 'Sem condição';
};
$mapPoints = array_map(static function (array $row) {
    return [
        'id' => (int) ($row['id'] ?? 0),
        'protocolo' => (string) ($row['protocolo'] ?? ''),
        'bairro' => (string) ($row['bairro_comunidade'] ?? ''),
        'endereco' => (string) ($row['endereco'] ?? ''),
        'condicao' => (string) (($row['condicao_residencia'] ?? '') !== '' ? $row['condicao_residencia'] : 'sem_condicao'),
        'condicao_label' => ($row['condicao_residencia'] ?? '') !== '' ? residencia_condicao_label($row['condicao_residencia']) : 'Sem condição',
        'imovel' => residencia_imovel_label($row['imovel'] ?? null),
        'latitude' => (float) ($row['latitude'] ?? 0),
        'longitude' => (float) ($row['longitude'] ?? 0),
        'familias' => (int) ($row['familias'] ?? 0),
        'familias_atendidas' => (int) ($row['familias_atendidas'] ?? 0),
        'acao' => trim((string) ($row['localidade'] ?? '') . ' - ' . (string) ($row['tipo_evento'] ?? '')),
        'municipio' => trim((string) ($row['municipio_nome'] ?? '') . '/' . (string) ($row['uf'] ?? '')),
        'url' => url('/cadastros/residencias/' . (int) ($row['id'] ?? 0)),
    ];
}, $mapResidences);
$mapJson = json_encode($mapPoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$mapJson = is_string($mapJson) ? $mapJson : '[]';
$totalResidencias = (int) ($indicators['residencias'] ?? 0);
$totalGeoref = (int) ($indicators['georreferenciadas'] ?? 0);
$semGeo = (int) ($indicators['sem_georreferencia'] ?? 0);
?>

<section class="ops-dashboard-page">
    <header class="ops-hero">
        <div>
            <span class="eyebrow">Centro operacional</span>
            <h1><?= h($title) ?></h1>
            <p>Painel georreferenciado para acompanhar residências, famílias, entregas e criticidade por condição da residência.</p>
        </div>
        <div class="ops-hero-status">
            <span>Atualizado em</span>
            <strong><?= h($generatedAt) ?></strong>
            <small><?= ($scope['is_cadastrador'] ?? false) ? 'Escopo restrito ao cadastrador e ação ativa.' : 'Visão consolidada conforme perfil logado.' ?></small>
        </div>
    </header>

    <section class="records-filter-panel report-filter-modern-panel ops-filter-panel no-print" aria-label="Filtros inteligentes do painel">
        <div class="ops-filter-heading">
            <div>
                <span class="eyebrow">Filtros inteligentes</span>
                <h2>Recorte operacional</h2>
            </div>
            <small><?= h(count($mapResidences)) ?> residência(s) plotada(s) no mapa</small>
        </div>

        <form method="get" action="<?= h(url('/dashboard')) ?>" class="report-filter-modern-form ops-filter-form">
            <label class="field styled-field report-filter-field report-filter-field-wide">
                <span>Busca geral</span>
                <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" placeholder="Protocolo, bairro, endereço, município ou evento">
            </label>

            <label class="field styled-field smart-search-field report-filter-field report-filter-field-wide">
                <span>Ação emergencial</span>
                <input type="search" name="acao_busca" value="<?= h(($filters['acao_busca'] ?? '') !== '' ? $filters['acao_busca'] : $selectedActionLabel) ?>" list="dashboard-acoes-list" placeholder="Digite município, localidade, evento, status ou ação #ID" data-smart-search data-smart-target="dashboard_acao_id" autocomplete="off">
                <input type="hidden" name="acao_id" value="<?= h($filters['acao_id'] ?? '') ?>" data-smart-hidden="dashboard_acao_id">
                <datalist id="dashboard-acoes-list">
                    <?php foreach ($actions as $action): ?>
                        <?php $actionLabel = trim((string) ($action['municipio_nome'] ?? '') . '/' . (string) ($action['uf'] ?? '') . ' - ' . (string) ($action['localidade'] ?? '') . ' - ' . (string) ($action['tipo_evento'] ?? '') . ' - ' . (string) ($action['status'] ?? '') . ' - ação #' . (string) ($action['id'] ?? '')); ?>
                        <option value="<?= h($actionLabel) ?>" data-id="<?= h($action['id'] ?? '') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-compact">
                <span>Condição</span>
                <select name="condicao">
                    <option value="">Todas</option>
                    <?php foreach (residencia_condicao_options() as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= ($filters['condicao'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-compact">
                <span>Imóvel</span>
                <select name="imovel">
                    <option value="">Todos</option>
                    <?php foreach (residencia_imovel_options() as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= ($filters['imovel'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-compact">
                <span>Entrega</span>
                <select name="entregas">
                    <option value="">Todas</option>
                    <option value="com_entrega" <?= ($filters['entregas'] ?? '') === 'com_entrega' ? 'selected' : '' ?>>Entregue</option>
                    <option value="sem_entrega" <?= ($filters['entregas'] ?? '') === 'sem_entrega' ? 'selected' : '' ?>>Sem entrega final</option>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-compact">
                <span>Geo</span>
                <select name="geo">
                    <option value="">Todas</option>
                    <option value="com_geo" <?= ($filters['geo'] ?? '') === 'com_geo' ? 'selected' : '' ?>>Com ponto</option>
                    <option value="sem_geo" <?= ($filters['geo'] ?? '') === 'sem_geo' ? 'selected' : '' ?>>Sem ponto</option>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-compact">
                <span>Cadastro</span>
                <select name="cadastro">
                    <option value="">Todos</option>
                    <option value="concluido" <?= ($filters['cadastro'] ?? '') === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                    <option value="pendente" <?= ($filters['cadastro'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-date">
                <span>Início</span>
                <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
            </label>

            <label class="field styled-field report-filter-field report-filter-field-date">
                <span>Fim</span>
                <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
            </label>

            <div class="records-filter-actions report-filter-actions ops-filter-actions">
                <button type="submit" class="primary-button">Aplicar filtros</button>
                <a class="secondary-button" href="<?= h(url('/dashboard')) ?>">Limpar</a>
            </div>
        </form>
    </section>

    <section class="ops-kpi-grid" aria-label="Indicadores operacionais">
        <article class="ops-kpi-card ops-kpi-card-critical">
            <span>Residências</span>
            <strong><?= h($formatInt($totalResidencias)) ?></strong>
            <small><?= h($formatInt($totalGeoref)) ?> com ponto no mapa</small>
        </article>
        <article class="ops-kpi-card">
            <span>Famílias</span>
            <strong><?= h($formatInt($indicators['familias'] ?? 0)) ?></strong>
            <small><?= h($formatInt($indicators['pessoas'] ?? 0)) ?> pessoa(s) declarada(s)</small>
        </article>
        <article class="ops-kpi-card">
            <span>Entregas</span>
            <strong><?= h($formatInt($indicators['entregas'] ?? 0)) ?></strong>
            <small><?= h($formatInt($indicators['familias_atendidas'] ?? 0)) ?> família(s) atendida(s)</small>
        </article>
        <article class="ops-kpi-card">
            <span>Georreferência</span>
            <strong><?= h($percent($totalGeoref, $totalResidencias)) ?></strong>
            <small><?= h($formatInt($semGeo)) ?> residência(s) sem coordenada</small>
        </article>
        <article class="ops-kpi-card">
            <span>Cadastros pendentes</span>
            <strong><?= h($formatInt($indicators['cadastros_pendentes'] ?? 0)) ?></strong>
            <small><?= h($formatInt($indicators['cadastros_concluidos'] ?? 0)) ?> concluído(s)</small>
        </article>
        <article class="ops-kpi-card">
            <span>Ações abertas</span>
            <strong><?= h($formatInt($indicators['acoes_abertas'] ?? 0)) ?></strong>
            <small><?= h($tiposAjudaAtivos) ?> tipo(s) de ajuda ativo(s)</small>
        </article>
    </section>

    <section class="ops-map-panel" aria-label="Mapa operacional georreferenciado">
        <div class="ops-map-head">
            <div>
                <span class="eyebrow">Mapa interativo</span>
                <h2>Residências georreferenciadas</h2>
                <p>Os marcadores em formato de casa mudam de cor conforme a condição da residência.</p>
            </div>
            <div class="ops-map-legend" aria-label="Legenda do mapa">
                <span><i class="ops-dot ops-dot-perda_total"></i>Perda total</span>
                <span><i class="ops-dot ops-dot-perda_parcial"></i>Perda parcial</span>
                <span><i class="ops-dot ops-dot-nao_atingida"></i>Não atingida</span>
                <span><i class="ops-dot ops-dot-sem_condicao"></i>Sem condição</span>
            </div>
        </div>

        <div class="ops-map-layout">
            <div class="ops-map-stage" data-dashboard-map data-map-points="<?= h($mapJson) ?>">
                <div class="ops-map-empty" data-map-empty>
                    <strong>Nenhuma residência georreferenciada neste recorte.</strong>
                    <span>Ajuste os filtros ou revise cadastros sem latitude/longitude.</span>
                </div>
            </div>

            <aside class="ops-map-sidebar">
                <article class="ops-map-details" data-map-details>
                    <span class="eyebrow">Ponto selecionado</span>
                    <h3>Selecione uma casa no mapa</h3>
                    <p>Ao clicar em um marcador, o painel mostra protocolo, bairro, ação, famílias e status de atendimento.</p>
                </article>

                <div class="ops-map-list" data-map-list>
                    <?php foreach (array_slice($mapPoints, 0, 8) as $point): ?>
                        <button type="button" data-map-focus="<?= h($point['id']) ?>" class="ops-map-list-item">
                            <span class="ops-mini-house ops-mini-house-<?= h($conditionClass($point['condicao'])) ?>"></span>
                            <span>
                                <strong><?= h($point['protocolo'] ?: 'Sem protocolo') ?></strong>
                                <small><?= h($point['bairro'] ?: '-') ?> - <?= h($point['condicao_label']) ?></small>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    </section>

    <section class="ops-intel-grid">
        <article class="ops-panel-card">
            <div class="ops-panel-head">
                <span class="eyebrow">Criticidade</span>
                <h2>Condição da residência</h2>
            </div>
            <div class="ops-condition-list">
                <?php if ($conditionBreakdown === []): ?>
                    <p class="empty-state">Nenhum dado no recorte atual.</p>
                <?php endif; ?>
                <?php foreach ($conditionBreakdown as $row): ?>
                    <?php $key = $conditionClass($row['condicao'] ?? ''); ?>
                    <div class="ops-condition-row">
                        <span class="ops-condition-label"><i class="ops-dot ops-dot-<?= h($key) ?>"></i><?= h($conditionLabel($row['condicao'] ?? '')) ?></span>
                        <strong><?= h($formatInt($row['residencias'] ?? 0)) ?></strong>
                        <small><?= h($formatInt($row['familias'] ?? 0)) ?> família(s)</small>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="ops-panel-card">
            <div class="ops-panel-head">
                <span class="eyebrow">Território</span>
                <h2>Bairros mais impactados</h2>
            </div>
            <div class="ops-ranking-list">
                <?php if ($neighborhoodRanking === []): ?>
                    <p class="empty-state">Nenhum bairro encontrado no recorte.</p>
                <?php endif; ?>
                <?php foreach ($neighborhoodRanking as $index => $row): ?>
                    <div class="ops-ranking-item">
                        <span><?= h(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                        <div>
                            <strong><?= h($row['bairro_comunidade'] ?? '-') ?></strong>
                            <small><?= h($row['municipio_nome'] ?? '-') ?>/<?= h($row['uf'] ?? '-') ?></small>
                        </div>
                        <em><?= h($formatInt($row['residencias'] ?? 0)) ?></em>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="ops-panel-card ops-recent-card">
            <div class="ops-panel-head">
                <span class="eyebrow">Últimas entradas</span>
                <h2>Residências cadastradas</h2>
            </div>
            <div class="ops-recent-list">
                <?php if ($recentResidences === []): ?>
                    <p class="empty-state">Nenhum cadastro encontrado.</p>
                <?php endif; ?>
                <?php foreach ($recentResidences as $row): ?>
                    <?php $geoOk = ($row['latitude'] ?? null) !== null && ($row['longitude'] ?? null) !== null; ?>
                    <a href="<?= h(url('/cadastros/residencias/' . (int) ($row['id'] ?? 0))) ?>" class="ops-recent-item">
                        <span class="ops-mini-house ops-mini-house-<?= h($conditionClass($row['condicao_residencia'] ?? '')) ?>"></span>
                        <span>
                            <strong><?= h($row['protocolo'] ?? '-') ?> - <?= h($row['bairro_comunidade'] ?? '-') ?></strong>
                            <small><?= h($row['municipio_nome'] ?? '-') ?>/<?= h($row['uf'] ?? '-') ?> - <?= h($geoOk ? 'com ponto' : 'sem ponto') ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </article>
    </section>
</section>
