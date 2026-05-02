<?php
$query = array_filter($filters, static fn ($value): bool => $value !== '');
$exportUrl = '/gestor/relatorios/exportar' . ($query !== [] ? '?' . http_build_query($query) : '');
$periodo = 'Todo o periodo';
if (($filters['data_inicio'] ?? '') !== '' || ($filters['data_fim'] ?? '') !== '') {
    $periodo = ($filters['data_inicio'] ?: 'inicio') . ' a ' . ($filters['data_fim'] ?: 'hoje');
}

$statusAcaoLabels = ['aberta' => 'Aberta', 'encerrada' => 'Encerrada', 'cancelada' => 'Cancelada'];
$entregaLabels = ['com_entrega' => 'Com entrega', 'sem_entrega' => 'Sem entrega'];
$cadastroLabels = ['concluido' => 'Concluido', 'pendente' => 'Pendente'];
$groupLabels = [
    'criancas' => 'Criancas',
    'idosos' => 'Idosos',
    'pcd' => 'PCD',
    'gestantes' => 'Gestantes',
    'beneficio_social' => 'Beneficio social',
];
$documentLabels = [
    'foto_residencia' => 'Foto da residencia',
    'foto_residencia_extra' => 'Fotos extras',
    'responsavel_documento' => 'Doc. responsavel',
    'representante_documento' => 'Doc. representante',
];
$signatureLabels = [
    'dti' => 'DTI',
    'prestacao_contas' => 'Prestacao de contas',
    'recomecar' => 'Programa Recomecar',
];
$filterLabels = [
    'q' => 'Busca',
    'acao_busca' => 'Acao',
    'tipo_ajuda_id' => 'Tipo de ajuda',
    'bairro' => 'Bairro',
    'status_acao' => 'Status da acao',
    'imovel' => 'Imovel',
    'condicao' => 'Condicao',
    'entregas' => 'Entrega',
    'cadastro' => 'Cadastro',
    'data_inicio' => 'Inicio',
    'data_fim' => 'Fim',
];
$formatInt = static fn (mixed $value): string => number_format((int) $value, 0, ',', '.');
$formatPercent = static function (mixed $part, mixed $total): string {
    $total = (int) $total;
    if ($total <= 0) {
        return '0%';
    }

    return number_format(min(100, ((int) $part / $total) * 100), 1, ',', '.') . '%';
};
$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp !== false ? date('d/m/Y', $timestamp) : '-';
};
$formatBytes = static function (mixed $bytes): string {
    $bytes = (float) $bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }

    return number_format($bytes / 1024, 1, ',', '.') . ' KB';
};
$filterValueLabel = static function (string $key, mixed $value) use ($acoes, $tiposAjuda, $statusAcaoLabels, $entregaLabels, $cadastroLabels, $formatDate): string {
    $value = (string) $value;
    if ($key === 'acao_busca') {
        return $value;
    }
    if ($key === 'acao_id') {
        foreach ($acoes as $acao) {
            if ((string) $acao['id'] === $value) {
                return $acao['municipio_nome'] . '/' . $acao['uf'] . ' - ' . $acao['localidade'];
            }
        }
    }
    if ($key === 'tipo_ajuda_id') {
        foreach ($tiposAjuda as $tipo) {
            if ((string) $tipo['id'] === $value) {
                return $tipo['nome'];
            }
        }
    }
    if ($key === 'status_acao') {
        return $statusAcaoLabels[$value] ?? $value;
    }
    if ($key === 'imovel') {
        return residencia_imovel_label($value);
    }
    if ($key === 'condicao') {
        return residencia_condicao_label($value);
    }
    if ($key === 'entregas') {
        return $entregaLabels[$value] ?? $value;
    }
    if ($key === 'cadastro') {
        return $cadastroLabels[$value] ?? $value;
    }
    if ($key === 'data_inicio' || $key === 'data_fim') {
        return $formatDate($value);
    }

    return $value;
};
$familias = (int) ($indicators['familias'] ?? 0);
$familiasAtendidas = (int) ($indicators['familias_atendidas'] ?? 0);
$familiasPendentes = (int) ($indicators['familias_pendentes'] ?? 0);
$cadastrosConcluidos = (int) ($indicators['cadastros_concluidos'] ?? 0);
$residencias = (int) ($indicators['residencias'] ?? 0);
$residenciasFoto = (int) ($indicators['residencias_com_foto'] ?? 0);
$recomecarAptas = (int) ($recomecarStats['aptas'] ?? 0);
$selectedActionLabel = '';
foreach ($acoes as $acao) {
    if ((string) ($filters['acao_id'] ?? '') === (string) $acao['id']) {
        $selectedActionLabel = $acao['municipio_nome'] . '/' . $acao['uf'] . ' - ' . $acao['localidade'] . ' - ' . $acao['tipo_evento'] . ' (' . $statusAcaoLabels[$acao['status']] . ')';
        break;
    }
}
$activeFilters = array_filter($filters, static fn ($value): bool => $value !== '');
if (($activeFilters['acao_busca'] ?? '') !== '') {
    unset($activeFilters['acao_id']);
} elseif (($activeFilters['acao_id'] ?? '') !== '') {
    $activeFilters['acao_busca'] = $filterValueLabel('acao_id', $activeFilters['acao_id']);
    unset($activeFilters['acao_id']);
}
?>

<section class="records-page report-page">
    <header class="dashboard-header records-header report-hero no-print">
        <div>
            <span class="eyebrow">Gestao operacional</span>
            <h1>Relatorios operacionais</h1>
            <p>Leitura consolidada dos cadastros, familias, entregas, documentos, assinaturas e elegibilidade do Programa Recomecar.</p>
        </div>
        <div class="report-hero-actions">
            <a class="primary-link-button" href="<?= h(url($exportUrl)) ?>">Exportar CSV</a>
            <button type="button" class="secondary-button" data-report-print-document>Imprimir</button>
        </div>
    </header>

    <section class="records-filter-panel report-filter-panel report-filter-modern-panel no-print" aria-label="Filtros de relatorios">
        <div class="table-heading">
            <div>
                <h2>Filtros inteligentes</h2>
                <span>Combine acao, texto, status, tipo de ajuda, periodo e situacao cadastral para montar uma visao operacional precisa.</span>
            </div>
        </div>

        <form method="get" action="<?= h(url('/gestor/relatorios')) ?>" class="report-filter-form report-filter-modern-form">
            <label class="field styled-field report-filter-field report-filter-field-wide">
                <span>Busca</span>
                <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" maxlength="180" placeholder="Protocolo, familia, CPF, telefone, acao ou endereco">
            </label>

            <label class="field styled-field smart-search-field report-filter-field report-filter-field-wide">
                <span>Acao emergencial</span>
                <input type="search" name="acao_busca" value="<?= h(($filters['acao_busca'] ?? '') !== '' ? $filters['acao_busca'] : $selectedActionLabel) ?>" list="relatorio-acoes-list" placeholder="Digite municipio, localidade, evento, status ou acao #ID" data-smart-search data-smart-target="relatorio_acao_id" autocomplete="off">
                <input type="hidden" name="acao_id" value="<?= h($filters['acao_id'] ?? '') ?>" data-smart-hidden="relatorio_acao_id">
                <datalist id="relatorio-acoes-list">
                    <?php foreach ($acoes as $acao): ?>
                        <?php $acaoLabel = $acao['municipio_nome'] . '/' . $acao['uf'] . ' - ' . $acao['localidade'] . ' - ' . $acao['tipo_evento'] . ' (' . ($statusAcaoLabels[$acao['status']] ?? $acao['status']) . ')'; ?>
                        <option value="<?= h($acaoLabel) ?>" data-id="<?= h($acao['id']) ?>"></option>
                        <option value="<?= h('Acao #' . $acao['id'] . ' - ' . $acaoLabel) ?>" data-id="<?= h($acao['id']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-medium">
                <span>Bairro/comunidade</span>
                <input type="search" name="bairro" value="<?= h($filters['bairro'] ?? '') ?>" maxlength="180" placeholder="Digite o bairro">
            </label>

            <label class="field styled-field report-filter-field report-filter-field-medium">
                <span>Tipo de ajuda</span>
                <select name="tipo_ajuda_id">
                    <option value="">Todos</option>
                    <?php foreach ($tiposAjuda as $tipo): ?>
                        <option value="<?= h($tipo['id']) ?>" <?= (string) ($filters['tipo_ajuda_id'] ?? '') === (string) $tipo['id'] ? 'selected' : '' ?>>
                            <?= h($tipo['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-compact">
                <span>Status acao</span>
                <select name="status_acao">
                    <option value="">Todos</option>
                    <?php foreach ($statusAcaoLabels as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['status_acao'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-compact">
                <span>Imovel</span>
                <select name="imovel">
                    <option value="">Todos</option>
                    <?php foreach (residencia_imovel_options() as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['imovel'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-compact">
                <span>Condicao</span>
                <select name="condicao">
                    <option value="">Todas</option>
                    <?php foreach (residencia_condicao_options() as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['condicao'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-compact">
                <span>Entregas</span>
                <select name="entregas">
                    <option value="">Todas</option>
                    <?php foreach ($entregaLabels as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['entregas'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-compact">
                <span>Cadastro</span>
                <select name="cadastro">
                    <option value="">Todos</option>
                    <?php foreach ($cadastroLabels as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['cadastro'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field report-filter-field report-filter-field-date">
                <span>Inicio</span>
                <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
            </label>

            <label class="field styled-field report-filter-field report-filter-field-date">
                <span>Fim</span>
                <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
            </label>

            <div class="records-filter-actions report-filter-actions">
                <button type="submit" class="primary-button">Filtrar</button>
                <a class="secondary-button" href="<?= h(url('/gestor/relatorios')) ?>">Limpar</a>
            </div>
        </form>

        <?php if ($activeFilters !== []): ?>
            <div class="records-active-filters" aria-label="Filtros ativos">
                <?php foreach ($activeFilters as $key => $value): ?>
                    <span><?= h(($filterLabels[$key] ?? $key) . ': ' . $filterValueLabel((string) $key, $value)) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="report-document">
        <div class="print-heading">
            <span class="eyebrow">Cadastro Emergencial</span>
            <h1>Relatorio operacional</h1>
            <p>Periodo: <?= h($periodo) ?> | Gerado em <?= h($generatedAt->format('d/m/Y H:i')) ?></p>
        </div>

        <section class="report-kpi-grid" aria-label="Indicadores do relatorio">
            <article class="report-kpi-card">
                <span>Residencias</span>
                <strong><?= h($formatInt($residencias)) ?></strong>
                <small><?= h($formatPercent($residenciasFoto, $residencias)) ?> com foto principal</small>
            </article>
            <article class="report-kpi-card">
                <span>Familias</span>
                <strong><?= h($formatInt($familias)) ?></strong>
                <small><?= h($formatPercent($cadastrosConcluidos, $familias)) ?> com cadastro concluido</small>
            </article>
            <article class="report-kpi-card">
                <span>Pessoas</span>
                <strong><?= h($formatInt($indicators['pessoas'] ?? 0)) ?></strong>
                <small>Integrantes declarados nos cadastros</small>
            </article>
            <article class="report-kpi-card">
                <span>Atendimento</span>
                <strong><?= h($formatPercent($familiasAtendidas, $familias)) ?></strong>
                <small><?= h($formatInt($familiasAtendidas)) ?> atendida(s), <?= h($formatInt($familiasPendentes)) ?> pendente(s)</small>
            </article>
            <article class="report-kpi-card">
                <span>Entregas</span>
                <strong><?= h($formatInt($indicators['entregas'] ?? 0)) ?></strong>
                <small><?= h(number_format((float) ($indicators['quantidade_entregue'] ?? 0), 2, ',', '.')) ?> itens/quantidade</small>
            </article>
            <article class="report-kpi-card">
                <span>Recomecar</span>
                <strong><?= h($formatInt($recomecarAptas)) ?></strong>
                <small>familia(s) apta(s) ao programa no recorte</small>
            </article>
        </section>

        <section class="report-insight-grid no-print" aria-label="Leituras rapidas">
            <article class="report-insight-card">
                <span>Prioridade de campo</span>
                <strong><?= h($familiasPendentes > 0 ? $formatInt($familiasPendentes) . ' familia(s) sem entrega' : 'Sem pendencias de entrega') ?></strong>
                <small>Use a lista de pendencias no fim do relatorio para acionar equipes de distribuicao.</small>
            </article>
            <article class="report-insight-card">
                <span>Qualidade do cadastro</span>
                <strong><?= h($formatPercent($registrationQuality['familias_concluidas'] ?? 0, $registrationQuality['familias'] ?? 0)) ?></strong>
                <small>Cadastros completos reduzem retrabalho em prestacao de contas, DTI e Recomecar.</small>
            </article>
            <article class="report-insight-card">
                <span>Documentos e assinaturas</span>
                <strong><?= h($formatInt(array_sum(array_map(static fn ($item): int => (int) ($item['arquivos'] ?? 0), $documentStats)))) ?> anexo(s)</strong>
                <small><?= h($formatInt(array_sum(array_map(static fn ($item): int => (int) ($item['pendentes'] ?? 0), $signatureStats)))) ?> assinatura(s) pendente(s).</small>
            </article>
        </section>

        <section class="table-panel report-section">
            <div class="table-heading">
                <h2>Resumo por acao emergencial</h2>
                <span><?= h(count($byAction)) ?> acao(oes)</span>
            </div>
            <table class="data-table report-data-table">
                <thead>
                    <tr>
                        <th>Municipio</th>
                        <th>Acao</th>
                        <th>Status</th>
                        <th>Residencias</th>
                        <th>Familias</th>
                        <th>Pessoas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byAction as $item): ?>
                        <tr>
                            <td data-label="Municipio"><?= h($item['municipio_nome']) ?>/<?= h($item['uf']) ?></td>
                            <td data-label="Acao"><?= h($item['localidade']) ?> - <?= h($item['tipo_evento']) ?></td>
                            <td data-label="Status"><span class="status status-<?= h($item['status']) ?>"><?= h($statusAcaoLabels[$item['status']] ?? ucfirst((string) $item['status'])) ?></span></td>
                            <td data-label="Residencias"><?= h($formatInt($item['residencias'])) ?></td>
                            <td data-label="Familias"><?= h($formatInt($item['familias'])) ?></td>
                            <td data-label="Pessoas"><?= h($formatInt($item['pessoas'])) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($byAction === []): ?>
                        <tr><td colspan="6" class="empty-state">Nenhum cadastro encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="report-panel-grid">
            <article class="table-panel report-section">
                <div class="table-heading">
                    <h2>Territorio</h2>
                    <span><?= h(count($byNeighborhood)) ?> bairro(s)</span>
                </div>
                <table class="data-table compact-table report-data-table">
                    <thead><tr><th>Bairro/comunidade</th><th>Familias</th><th>Pessoas</th></tr></thead>
                    <tbody>
                        <?php foreach ($byNeighborhood as $item): ?>
                            <tr>
                                <td data-label="Bairro"><?= h($item['bairro_comunidade']) ?><br><small><?= h($item['municipio_nome']) ?>/<?= h($item['uf']) ?></small></td>
                                <td data-label="Familias"><?= h($formatInt($item['familias'])) ?></td>
                                <td data-label="Pessoas"><?= h($formatInt($item['pessoas'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($byNeighborhood === []): ?><tr><td colspan="3" class="empty-state">Sem dados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </article>

            <article class="table-panel report-section">
                <div class="table-heading">
                    <h2>Imovel e dano</h2>
                    <span>Classificacao do cadastro</span>
                </div>
                <div class="report-mini-tables">
                    <table class="data-table compact-table report-data-table">
                        <thead><tr><th>Imovel</th><th>Residencias</th><th>Familias</th></tr></thead>
                        <tbody>
                            <?php foreach ($byHousingType as $item): ?>
                                <tr>
                                    <td data-label="Imovel"><?= h(residencia_imovel_label($item['imovel'] ?? null)) ?></td>
                                    <td data-label="Residencias"><?= h($formatInt($item['residencias'])) ?></td>
                                    <td data-label="Familias"><?= h($formatInt($item['familias'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <table class="data-table compact-table report-data-table">
                        <thead><tr><th>Condicao</th><th>Residencias</th><th>Familias</th></tr></thead>
                        <tbody>
                            <?php foreach ($byResidenceCondition as $item): ?>
                                <tr>
                                    <td data-label="Condicao"><?= h(residencia_condicao_label($item['condicao_residencia'] ?? null)) ?></td>
                                    <td data-label="Residencias"><?= h($formatInt($item['residencias'])) ?></td>
                                    <td data-label="Familias"><?= h($formatInt($item['familias'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="report-panel-grid">
            <article class="table-panel report-section">
                <div class="table-heading">
                    <h2>Entregas por tipo</h2>
                    <span><?= h(count($deliveriesByType)) ?> tipo(s)</span>
                </div>
                <table class="data-table compact-table report-data-table">
                    <thead><tr><th>Ajuda</th><th>Familias</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($deliveriesByType as $item): ?>
                            <tr>
                                <td data-label="Ajuda"><?= h($item['nome']) ?></td>
                                <td data-label="Familias"><?= h($formatInt($item['familias_atendidas'])) ?></td>
                                <td data-label="Total"><?= h($formatInt(round((float) $item['quantidade_total']))) ?> <?= h($item['unidade_medida']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($deliveriesByType === []): ?><tr><td colspan="3" class="empty-state">Sem entregas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </article>

            <article class="table-panel report-section">
                <div class="table-heading">
                    <h2>Ultimos dias de entrega</h2>
                    <span><?= h(count($deliveryTimeline)) ?> dia(s)</span>
                </div>
                <table class="data-table compact-table report-data-table">
                    <thead><tr><th>Data</th><th>Familias</th><th>Entregas</th><th>Qtd.</th></tr></thead>
                    <tbody>
                        <?php foreach ($deliveryTimeline as $item): ?>
                            <tr>
                                <td data-label="Data"><?= h($formatDate($item['data'])) ?></td>
                                <td data-label="Familias"><?= h($formatInt($item['familias_atendidas'])) ?></td>
                                <td data-label="Entregas"><?= h($formatInt($item['entregas'])) ?></td>
                                <td data-label="Qtd."><?= h($formatInt(round((float) $item['quantidade_total']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($deliveryTimeline === []): ?><tr><td colspan="4" class="empty-state">Sem entregas no recorte.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </article>
        </section>

        <section class="report-card-grid">
            <article class="report-metric-panel">
                <h2>Grupos prioritarios</h2>
                <?php foreach ($vulnerableGroups as $item): ?>
                    <div class="report-meter-row">
                        <span><?= h($groupLabels[$item['grupo']] ?? $item['grupo']) ?></span>
                        <strong><?= h($formatInt($item['familias'])) ?></strong>
                    </div>
                <?php endforeach; ?>
            </article>

            <article class="report-metric-panel">
                <h2>Qualidade do cadastro</h2>
                <div class="report-meter-row"><span>Familias concluidas</span><strong><?= h($formatPercent($registrationQuality['familias_concluidas'] ?? 0, $registrationQuality['familias'] ?? 0)) ?></strong></div>
                <div class="report-meter-row"><span>Com telefone</span><strong><?= h($formatPercent($registrationQuality['familias_com_telefone'] ?? 0, $registrationQuality['familias'] ?? 0)) ?></strong></div>
                <div class="report-meter-row"><span>Com email</span><strong><?= h($formatPercent($registrationQuality['familias_com_email'] ?? 0, $registrationQuality['familias'] ?? 0)) ?></strong></div>
                <div class="report-meter-row"><span>Residencias georreferenciadas</span><strong><?= h($formatPercent($registrationQuality['com_geolocalizacao'] ?? 0, $registrationQuality['residencias'] ?? 0)) ?></strong></div>
            </article>

            <article class="report-metric-panel">
                <h2>Documentos anexados</h2>
                <?php foreach ($documentStats as $item): ?>
                    <div class="report-meter-row">
                        <span><?= h($documentLabels[$item['tipo_documento']] ?? $item['tipo_documento']) ?></span>
                        <strong><?= h($formatInt($item['arquivos'])) ?> <small><?= h($formatBytes($item['tamanho_total'])) ?></small></strong>
                    </div>
                <?php endforeach; ?>
                <?php if ($documentStats === []): ?><p class="empty-state">Sem anexos no recorte.</p><?php endif; ?>
            </article>

            <article class="report-metric-panel">
                <h2>Assinaturas digitais</h2>
                <?php foreach ($signatureStats as $item): ?>
                    <div class="report-meter-row">
                        <span><?= h($signatureLabels[$item['documento_tipo']] ?? $item['documento_tipo']) ?></span>
                        <strong><?= h($formatInt($item['autorizadas'])) ?>/<?= h($formatInt($item['total'])) ?></strong>
                    </div>
                <?php endforeach; ?>
                <?php if ($signatureStats === []): ?><p class="empty-state">Sem assinaturas no periodo.</p><?php endif; ?>
            </article>
        </section>

        <section class="table-panel report-section">
            <div class="table-heading">
                <h2>Pendencias de entrega</h2>
                <span><?= h(count($pendingFamilies)) ?> exibida(s)</span>
            </div>
            <table class="data-table report-data-table">
                <thead>
                    <tr>
                        <th>Familia</th>
                        <th>CPF</th>
                        <th>Telefone</th>
                        <th>Residencia</th>
                        <th>Acao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingFamilies as $item): ?>
                        <tr>
                            <td data-label="Familia"><?= h($item['responsavel_nome']) ?></td>
                            <td data-label="CPF"><?= h($item['responsavel_cpf']) ?></td>
                            <td data-label="Telefone"><?= h($item['telefone'] ?: '-') ?></td>
                            <td data-label="Residencia">
                                <a href="<?= h(url('/cadastros/residencias/' . $item['residencia_id'])) ?>"><?= h($item['protocolo']) ?></a><br>
                                <small><?= h($item['bairro_comunidade']) ?> - <?= h($item['municipio_nome']) ?>/<?= h($item['uf']) ?></small>
                            </td>
                            <td data-label="Acao"><?= h($item['localidade']) ?> - <?= h($item['tipo_evento']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($pendingFamilies === []): ?>
                        <tr><td colspan="5" class="empty-state">Nenhuma pendencia encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </section>
</section>

<script>
    (function () {
        'use strict';

        function preparePrint() {
            document.body.classList.add('is-printing-report-document');
        }

        function restoreScreen() {
            document.body.classList.remove('is-printing-report-document');
        }

        document.querySelectorAll('[data-report-print-document]').forEach(function (button) {
            button.addEventListener('click', function () {
                preparePrint();
                window.print();
            });
        });

        window.addEventListener('beforeprint', preparePrint);
        window.addEventListener('afterprint', restoreScreen);
    })();
</script>
