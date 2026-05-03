<?php
$filters = $filters ?? [
    'q' => '',
    'status' => '',
    'unidade' => '',
];
$summary = $summary ?? [];
$pagination = $pagination ?? [
    'page' => 1,
    'per_page' => 5,
    'total' => count($tipos),
    'total_pages' => 1,
];
$allTipos = $allTipos ?? $tipos;
$unidades = $unidades ?? [];
$totalTipos = (int) ($summary['total_tipos'] ?? $pagination['total'] ?? 0);
$totalAtivos = (int) ($summary['ativos'] ?? 0);
$totalInativos = (int) ($summary['inativos'] ?? 0);
$totalUnidades = (int) ($summary['unidades'] ?? 0);
$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 5));
$firstRecord = $totalTipos > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastRecord = min($totalTipos, $page * $perPage);
$statusLabels = [
    'ativo' => 'Ativo',
    'inativo' => 'Inativo',
];
$activeFilters = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
$filterLabels = [
    'q' => 'Busca',
    'status' => 'Status',
    'unidade' => 'Unidade',
];
$filterValueLabel = static function (string $key, string $value) use ($statusLabels): string {
    if ($key === 'status') {
        return $statusLabels[$value] ?? $value;
    }

    return $value;
};
$pageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
    $query['pagina'] = max(1, $targetPage);

    return url('/admin/ajudas' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<section class="records-page aid-types-page">
    <header class="action-form-header records-header">
        <div>
            <span class="eyebrow">Administração</span>
            <h1>Tipos de ajuda humanitária</h1>
            <p>Materiais que podem ser vinculados às entregas, comprovantes e prestação de contas.</p>
        </div>
        <a class="primary-link-button" href="<?= h(url('/admin/ajudas/novo')) ?>">Novo tipo</a>
    </header>

    <section class="records-summary-grid aid-types-summary-grid" aria-label="Resumo dos tipos de ajuda">
        <article class="records-summary-card">
            <span>Tipos cadastrados</span>
            <strong><?= h($totalTipos) ?></strong>
            <small><?= $activeFilters === [] ? 'Total de materiais configurados.' : 'Total encontrado pelos filtros.' ?></small>
        </article>
        <article class="records-summary-card">
            <span>Ativos</span>
            <strong><?= h($totalAtivos) ?></strong>
            <small>Disponíveis para registro de entrega.</small>
        </article>
        <article class="records-summary-card">
            <span>Inativos</span>
            <strong><?= h($totalInativos) ?></strong>
            <small>Ocultos das novas entregas.</small>
        </article>
        <article class="records-summary-card">
            <span>Unidades de medida</span>
            <strong><?= h($totalUnidades) ?></strong>
            <small>Tipos de unidade diferentes cadastrados.</small>
        </article>
    </section>

    <section class="records-filter-panel aid-type-filter-panel aid-type-filter-modern-panel" aria-label="Filtros de tipos de ajuda">
        <form method="get" action="<?= h(url('/admin/ajudas')) ?>" class="aid-type-filter-form aid-type-filter-modern-form">
            <label class="field styled-field aid-type-search-field aid-type-filter-field aid-type-filter-field-wide">
                <span>Busca inteligente</span>
                <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" maxlength="120" list="aid-type-search-list" placeholder="Nome do material ou unidade">
                <datalist id="aid-type-search-list">
                    <?php foreach ($allTipos as $tipoOpcao): ?>
                        <option value="<?= h($tipoOpcao['nome']) ?>"></option>
                        <option value="<?= h($tipoOpcao['unidade_medida']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="field styled-field aid-type-filter-field aid-type-filter-field-compact">
                <span>Status</span>
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['status'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field aid-type-filter-field aid-type-filter-field-medium">
                <span>Unidade</span>
                <input type="search" name="unidade" value="<?= h($filters['unidade'] ?? '') ?>" maxlength="50" list="aid-type-unit-list" placeholder="kit, cesta, unidade">
                <datalist id="aid-type-unit-list">
                    <?php foreach ($unidades as $unidade): ?>
                        <option value="<?= h($unidade['unidade_medida']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <div class="records-filter-actions aid-type-filter-actions">
                <button type="submit" class="primary-button">Filtrar</button>
                <a class="secondary-button" href="<?= h(url('/admin/ajudas')) ?>">Limpar</a>
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
        <span><?= h($totalTipos) ?> tipo(s) encontrado(s)</span>
        <strong><?= h($firstRecord) ?>-<?= h($lastRecord) ?> de <?= h($totalTipos) ?></strong>
    </div>

    <?php if ($tipos === []): ?>
        <section class="action-empty-panel records-empty-panel">
            <h2><?= $activeFilters === [] ? 'Nenhum tipo de ajuda cadastrado' : 'Nenhum tipo de ajuda encontrado' ?></h2>
            <p><?= $activeFilters === [] ? 'Cadastre os materiais que serão utilizados nas entregas e comprovantes.' : 'Revise os filtros aplicados ou limpe a busca para voltar à lista completa.' ?></p>
            <?php if ($activeFilters !== []): ?>
                <a class="primary-link-button" href="<?= h(url('/admin/ajudas')) ?>">Limpar filtros</a>
            <?php else: ?>
                <a class="primary-link-button" href="<?= h(url('/admin/ajudas/novo')) ?>">Criar primeiro tipo</a>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="aid-type-card-list" aria-label="Lista de tipos de ajuda">
            <?php foreach ($tipos as $tipo): ?>
                <?php
                $ativo = (int) ($tipo['ativo'] ?? 0) === 1;
                $entregasRegistradas = (int) ($tipo['entregas_registradas'] ?? 0);
                $canDelete = $entregasRegistradas === 0;
                $dataCadastro = !empty($tipo['criado_em']) ? strtotime((string) $tipo['criado_em']) : false;
                ?>
                <article class="aid-type-card">
                    <div class="aid-type-main">
                        <div class="aid-type-title">
                            <span class="status <?= $ativo ? 'status-aberta' : 'status-cancelada' ?>"><?= $ativo ? 'Ativo' : 'Inativo' ?></span>
                            <h2><?= h($tipo['nome']) ?></h2>
                            <p><?= $ativo ? 'Disponível para novas entregas.' : 'Indisponível para novas entregas.' ?></p>
                        </div>

                        <dl class="aid-type-meta">
                            <div>
                                <dt>Unidade</dt>
                                <dd><?= h($tipo['unidade_medida']) ?></dd>
                            </div>
                            <div>
                                <dt>Criado em</dt>
                                <dd><?= $dataCadastro !== false ? h(date('d/m/Y H:i', $dataCadastro)) : '-' ?></dd>
                            </div>
                            <div>
                                <dt>Entregas</dt>
                                <dd><?= h($entregasRegistradas) ?></dd>
                            </div>
                        </dl>
                    </div>

                    <aside class="aid-type-actions" aria-label="Ações do tipo de ajuda">
                        <a class="secondary-button action-card-button" href="<?= h(url('/admin/ajudas/' . $tipo['id'] . '/editar')) ?>">Editar</a>

                        <?php if ($ativo): ?>
                            <form method="post" action="<?= h(url('/admin/ajudas/' . $tipo['id'] . '/inativar')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Inativar este tipo de ajuda? Ele não aparecerá em novas entregas.">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.ajudas.status.' . $tipo['id'] . '.inativar') ?>
                                <button type="submit" class="secondary-button action-card-button" data-loading-text="Inativando...">Inativar</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= h(url('/admin/ajudas/' . $tipo['id'] . '/ativar')) ?>" class="inline-form js-prevent-double-submit">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.ajudas.status.' . $tipo['id'] . '.ativar') ?>
                                <button type="submit" class="secondary-button action-card-button" data-loading-text="Ativando...">Ativar</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= h(url('/admin/ajudas/' . $tipo['id'] . '/excluir')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Excluir definitivamente este tipo de ajuda?">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.ajudas.delete.' . $tipo['id']) ?>
                                <button type="submit" class="danger-button action-card-button" data-loading-text="Excluindo...">Excluir</button>
                            </form>
                        <?php else: ?>
                            <span class="action-card-disabled">Exclusão bloqueada</span>
                        <?php endif; ?>
                    </aside>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="records-pagination" aria-label="Paginação dos tipos de ajuda">
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
