<?php
$filters = $filters ?? [
    'q' => '',
    'imovel' => '',
    'condicao' => '',
    'familias' => '',
    'data_inicio' => '',
    'data_fim' => '',
];
$summary = $summary ?? [];
$pagination = $pagination ?? [
    'page' => 1,
    'per_page' => 10,
    'total' => count($residencias),
    'total_pages' => 1,
];
$totalResidencias = (int) ($summary['total_residencias'] ?? $pagination['total'] ?? 0);
$totalFamilias = (int) ($summary['total_familias'] ?? 0);
$totalCapacidade = (int) ($summary['total_capacidade'] ?? 0);
$condicoes = [
    'perda_total' => (int) ($summary['perda_total'] ?? 0),
    'perda_parcial' => (int) ($summary['perda_parcial'] ?? 0),
    'nao_atingida' => (int) ($summary['nao_atingida'] ?? 0),
];
arsort($condicoes);
$condicaoPrincipalKey = (string) array_key_first($condicoes);
$condicaoPrincipal = ($condicoes[$condicaoPrincipalKey] ?? 0) > 0
    ? residencia_condicao_label($condicaoPrincipalKey)
    : '-';
$ultimaAtualizacao = !empty($summary['ultima_atualizacao'])
    ? strtotime((string) $summary['ultima_atualizacao'])
    : null;
$activeActionToken = App\Core\Session::get('active_action_token');
$novoCadastroUrl = is_string($activeActionToken) && $activeActionToken !== ''
    ? '/acao/' . rawurlencode($activeActionToken) . '/residencias/novo'
    : null;
$activeFilters = array_filter($filters, static fn (mixed $value): bool => $value !== '');
$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 10));
$firstRecord = $totalResidencias > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastRecord = min($totalResidencias, $page * $perPage);
$familiaFilterLabels = [
    'completas' => 'Completas',
    'pendentes' => 'Pendentes',
    'sem_familias' => 'Sem famílias',
];
$filterLabels = [
    'q' => 'Busca',
    'imovel' => 'Imóvel',
    'condicao' => 'Condição',
    'familias' => 'Famílias',
    'data_inicio' => 'Início',
    'data_fim' => 'Fim',
];
$filterValueLabel = static function (string $key, string $value) use ($familiaFilterLabels): string {
    if ($key === 'imovel') {
        return residencia_imovel_label($value);
    }

    if ($key === 'condicao') {
        return residencia_condicao_label($value);
    }

    if ($key === 'familias') {
        return $familiaFilterLabels[$value] ?? $value;
    }

    if (($key === 'data_inicio' || $key === 'data_fim') && strtotime($value) !== false) {
        return date('d/m/Y', strtotime($value));
    }

    return $value;
};
$pageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter($filters, static fn (mixed $value): bool => $value !== '');
    $query['pagina'] = max(1, $targetPage);

    return url('/cadastros/residencias' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
$filterAction = url('/cadastros/residencias');
?>

<section class="records-page">
    <header class="action-form-header records-header">
        <div>
            <span class="eyebrow">Cadastros</span>
            <h1>Residências cadastradas</h1>
            <p>Acompanhe casas atingidas, famílias vinculadas e situação dos imóveis por ação emergencial.</p>
        </div>
        <?php if ($novoCadastroUrl !== null): ?>
            <a class="primary-link-button" href="<?= h(url($novoCadastroUrl)) ?>">Novo cadastro</a>
        <?php endif; ?>
    </header>

    <section class="records-summary-grid" aria-label="Resumo dos cadastros">
        <article class="records-summary-card">
            <span>Residências</span>
            <strong><?= h($totalResidencias) ?></strong>
            <small><?= $activeFilters === [] ? 'Total registrado no escopo atual.' : 'Total encontrado pelos filtros.' ?></small>
        </article>
        <article class="records-summary-card">
            <span>Famílias</span>
            <strong><?= h($totalFamilias) ?> / <?= h($totalCapacidade) ?></strong>
            <small>Famílias cadastradas sobre a capacidade informada.</small>
        </article>
        <article class="records-summary-card">
            <span>Condição predominante</span>
            <strong><?= h($condicaoPrincipal) ?></strong>
            <small>Classificação mais recorrente na listagem.</small>
        </article>
        <article class="records-summary-card">
            <span>Último cadastro</span>
            <strong><?= $ultimaAtualizacao !== null ? h(date('d/m/Y', $ultimaAtualizacao)) : '-' ?></strong>
            <small><?= $ultimaAtualizacao !== null ? h(date('H:i', $ultimaAtualizacao)) : 'Sem registros' ?></small>
        </article>
    </section>

    <section class="records-filter-panel cadastro-filter-modern-panel" aria-label="Filtros de cadastros">
        <form method="get" action="<?= h($filterAction) ?>" class="records-filter-form cadastro-filter-modern-form">
            <label class="field styled-field records-search-field cadastro-filter-field cadastro-filter-field-wide">
                <span>Busca inteligente</span>
                <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" maxlength="120" placeholder="Protocolo, bairro, endereço, ação, município ou cadastrador">
            </label>

            <label class="field styled-field cadastro-filter-field cadastro-filter-field-compact">
                <span>Imóvel</span>
                <select name="imovel">
                    <option value="">Todos</option>
                    <?php foreach (residencia_imovel_options() as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['imovel'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field cadastro-filter-field cadastro-filter-field-compact">
                <span>Condição</span>
                <select name="condicao">
                    <option value="">Todas</option>
                    <?php foreach (residencia_condicao_options() as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['condicao'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field cadastro-filter-field cadastro-filter-field-compact">
                <span>Famílias</span>
                <select name="familias">
                    <option value="">Todas</option>
                    <?php foreach ($familiaFilterLabels as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['familias'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field cadastro-filter-field cadastro-filter-field-date">
                <span>Início</span>
                <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
            </label>

            <label class="field styled-field cadastro-filter-field cadastro-filter-field-date">
                <span>Fim</span>
                <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
            </label>

            <div class="records-filter-actions cadastro-filter-actions">
                <button type="submit" class="primary-button">Filtrar</button>
                <a class="secondary-button" href="<?= h(url('/cadastros/residencias')) ?>">Limpar</a>
            </div>
        </form>

        <?php if ($activeFilters !== []): ?>
            <div class="records-active-filters" aria-label="Filtros ativos">
                <?php foreach ($activeFilters as $key => $value): ?>
                    <span><?= h(($filterLabels[$key] ?? $key) . ': ' . $filterValueLabel((string) $key, (string) $value)) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="records-list-toolbar">
        <span><?= h($totalResidencias) ?> registro(s) encontrado(s)</span>
        <strong><?= h($firstRecord) ?>-<?= h($lastRecord) ?> de <?= h($totalResidencias) ?></strong>
    </div>

    <?php if ($residencias === []): ?>
        <section class="action-empty-panel records-empty-panel">
            <h2><?= $activeFilters === [] ? 'Nenhuma residência cadastrada' : 'Nenhum cadastro encontrado' ?></h2>
            <p><?= $activeFilters === [] ? 'Quando uma residência for registrada pelo sistema ou pelo aplicativo via QR Code, ela aparecerá aqui para acompanhamento.' : 'Revise os filtros aplicados ou limpe a busca para voltar à lista completa.' ?></p>
            <?php if ($activeFilters !== []): ?>
                <a class="primary-link-button" href="<?= h(url('/cadastros/residencias')) ?>">Limpar filtros</a>
            <?php elseif ($novoCadastroUrl !== null): ?>
                <a class="primary-link-button" href="<?= h(url($novoCadastroUrl)) ?>">Iniciar primeiro cadastro</a>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="records-card-grid" aria-label="Lista de residências cadastradas">
            <?php foreach ($residencias as $residencia): ?>
                <?php
                $familiasCadastradas = (int) ($residencia['familias_cadastradas'] ?? 0);
                $familiasPrevistas = max(1, (int) ($residencia['quantidade_familias'] ?? 1));
                $familiasPercentual = min(100, (int) round(($familiasCadastradas / $familiasPrevistas) * 100));
                $condicao = (string) ($residencia['condicao_residencia'] ?? '');
                $condicaoClass = $condicao !== '' ? preg_replace('/[^a-z0-9_-]+/i', '-', $condicao) : 'sem-condicao';
                $dataCadastro = strtotime((string) ($residencia['data_cadastro'] ?? ''));
                ?>
                <article class="record-card">
                    <div class="record-card-main">
                        <div class="record-card-title">
                            <span class="record-protocol"><?= h($residencia['protocolo']) ?></span>
                            <h2><?= h($residencia['bairro_comunidade']) ?></h2>
                            <p><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?> - <?= h($residencia['localidade']) ?></p>
                        </div>

                        <dl class="record-card-meta">
                            <div>
                                <dt>Evento</dt>
                                <dd><?= h($residencia['tipo_evento']) ?></dd>
                            </div>
                            <div>
                                <dt>Imóvel</dt>
                                <dd><?= h(residencia_imovel_label($residencia['imovel'] ?? null)) ?></dd>
                            </div>
                            <div>
                                <dt>Condição</dt>
                                <dd><span class="record-condition record-condition-<?= h($condicaoClass) ?>"><?= h(residencia_condicao_label($residencia['condicao_residencia'] ?? null)) ?></span></dd>
                            </div>
                            <div>
                                <dt>Cadastrador</dt>
                                <dd><?= h($residencia['cadastrador_nome']) ?></dd>
                            </div>
                        </dl>
                    </div>

                    <aside class="record-card-side" aria-label="Resumo da residência">
                        <div class="record-family-meter">
                            <div>
                                <span>Famílias</span>
                                <strong><?= h($familiasCadastradas) ?> / <?= h($familiasPrevistas) ?></strong>
                            </div>
                            <div class="record-progress" aria-hidden="true">
                                <span style="width: <?= h((string) $familiasPercentual) ?>%"></span>
                            </div>
                        </div>

                        <div class="record-card-date">
                            <span>Cadastrado em</span>
                            <strong><?= $dataCadastro !== false ? h(date('d/m/Y H:i', $dataCadastro)) : '-' ?></strong>
                        </div>

                        <a class="primary-link-button record-open-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'])) ?>">Abrir cadastro</a>
                    </aside>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="records-pagination" aria-label="Paginação de cadastros">
            <a class="pagination-link <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= h($page > 1 ? $pageUrl($page - 1) : '#') ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Anterior</a>

            <div class="pagination-pages">
                <?php for ($itemPage = 1; $itemPage <= $totalPages; $itemPage++): ?>
                    <?php if ($itemPage === 1 || $itemPage === $totalPages || abs($itemPage - $page) <= 1): ?>
                        <a class="pagination-number <?= $itemPage === $page ? 'is-active' : '' ?>" href="<?= h($pageUrl($itemPage)) ?>" aria-current="<?= $itemPage === $page ? 'page' : 'false' ?>"><?= h($itemPage) ?></a>
                    <?php elseif ($itemPage === 2 && $page > 3 || $itemPage === $totalPages - 1 && $page < $totalPages - 2): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>

            <a class="pagination-link <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h($page < $totalPages ? $pageUrl($page + 1) : '#') ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Próxima</a>
        </nav>
    <?php endif; ?>
</section>
