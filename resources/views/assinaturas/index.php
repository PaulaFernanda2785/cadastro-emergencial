<?php
$statusLabel = static function (mixed $status): string {
    return [
        'pendente' => 'Pendente',
        'autorizado' => 'Autorizado',
        'negado' => 'Nao autorizado',
        'cancelado' => 'Cancelado',
    ][(string) $status] ?? '-';
};
$documentLabel = static function (mixed $type): string {
    return [
        'dti' => 'DTI',
        'prestacao_contas' => 'Prestacao de contas',
        'recomecar' => 'Programa Recomecar',
    ][(string) $type] ?? (string) ($type ?: '-');
};
$scopeLabel = static function (mixed $scope): string {
    return [
        'todas' => 'Todas',
        'para_mim' => 'Para minha assinatura',
        'solicitadas' => 'Solicitadas por mim',
    ][(string) $scope] ?? 'Todas';
};
$formatDateTime = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '-';
};
$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp !== false ? date('d/m/Y', $timestamp) : '-';
};
$filters = $filters ?? [
    'escopo' => 'todas',
    'status' => '',
    'documento_tipo' => '',
    'data_inicio' => '',
    'data_fim' => '',
    'busca' => '',
];
$isAdmin = (bool) ($isAdmin ?? false);
$pagination = $pagination ?? ['page' => 1, 'per_page' => 10, 'total' => count($assinaturas ?? []), 'total_pages' => 1];
$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$perPage = min(10, max(1, (int) ($pagination['per_page'] ?? 10)));
$totalRecords = (int) ($pagination['total'] ?? count($assinaturas ?? []));
$queryForPagination = array_filter($filters, static fn ($value): bool => $value !== '' && $value !== 'todas');
$pageUrl = static function (int $targetPage) use ($queryForPagination): string {
    $query = $queryForPagination;
    $query['page'] = $targetPage;

    return '/assinaturas?' . http_build_query($query);
};
$visiblePages = static function (int $currentPage, int $lastPage): array {
    if ($lastPage <= 7) {
        return range(1, $lastPage);
    }

    $pages = [1];
    $start = max(2, $currentPage - 1);
    $end = min($lastPage - 1, $currentPage + 1);

    if ($start > 2) {
        $pages[] = '...';
    }

    for ($item = $start; $item <= $end; $item++) {
        $pages[] = $item;
    }

    if ($end < $lastPage - 1) {
        $pages[] = '...';
    }

    $pages[] = $lastPage;

    return $pages;
};
$hasFilters = array_filter($filters, static fn ($value): bool => $value !== '' && $value !== 'todas') !== [];
$firstRecord = $totalRecords > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastRecord = min($totalRecords, $page * $perPage);
?>

<section class="records-page signatures-page">
    <header class="dashboard-header signatures-header">
        <div>
            <span class="eyebrow">Assinaturas digitais</span>
            <h1>Assinaturas</h1>
            <p><?= $isAdmin ? 'Historico geral de documentos gerados, assinados e coassinados por todos os usuarios do sistema.' : 'Historico dos documentos que voce gerou, assinou ou recebeu como coautor.' ?></p>
        </div>
    </header>

    <section class="records-summary-grid signatures-summary-grid" aria-label="Resumo das assinaturas">
        <article class="records-summary-card">
            <span><?= $isAdmin ? 'Total no sistema' : 'Para minha assinatura' ?></span>
            <strong><?= h((int) ($isAdmin ? ($summary['total_sistema'] ?? 0) : ($summary['para_mim_total'] ?? 0))) ?></strong>
            <small><?= h((int) ($isAdmin ? ($summary['pendentes_sistema'] ?? 0) : ($summary['para_mim_pendentes'] ?? 0))) ?> pendente(s) de decisao.</small>
        </article>
        <article class="records-summary-card">
            <span><?= $isAdmin ? 'Controle total' : 'Solicitadas por mim' ?></span>
            <strong><?= h((int) ($isAdmin ? ($summary['total_sistema'] ?? 0) : ($summary['solicitadas_total'] ?? 0))) ?></strong>
            <small><?= $isAdmin ? 'Todos os registros e documentos disponiveis.' : h((int) ($summary['solicitadas_pendentes'] ?? 0)) . ' aguardando coautor.' ?></small>
        </article>
        <article class="records-summary-card">
            <span>Autorizadas</span>
            <strong><?= h((int) ($summary['autorizadas'] ?? 0)) ?></strong>
            <small>Assinaturas liberadas para o fluxo do documento.</small>
        </article>
        <article class="records-summary-card">
            <span>Nao autorizadas</span>
            <strong><?= h((int) ($summary['negadas'] ?? 0)) ?></strong>
            <small>Assinaturas recusadas por coautores.</small>
        </article>
    </section>

    <?php if (($pendentes ?? []) !== []): ?>
        <section class="signature-alert-panel" role="alert">
            <div>
                <span class="eyebrow">Pendencias</span>
                <h2>Voce possui <?= h(count($pendentes)) ?> documento(s) aguardando sua decisao</h2>
                <p>Use os filtros ou abra diretamente os itens pendentes para autorizar ou nao autorizar.</p>
            </div>
            <a class="primary-button" href="<?= h(url('/assinaturas?escopo=para_mim&status=pendente')) ?>">Ver pendencias</a>
        </section>
    <?php endif; ?>

    <section class="records-filter-panel signatures-filter-panel">
        <div class="table-heading">
            <div>
                <h2>Filtros inteligentes</h2>
                <span><?= $isAdmin ? 'Administrador visualiza todos os registros. Use os filtros para localizar documentos por usuario, status, tipo ou periodo.' : 'Seu perfil visualiza apenas documentos vinculados ao seu usuario como solicitante ou coautor.' ?></span>
            </div>
        </div>

        <form method="get" action="<?= h(url('/assinaturas')) ?>" class="signatures-filter-form">
            <label class="field styled-field records-search-field">
                <span>Buscar</span>
                <input type="text" name="busca" value="<?= h($filters['busca'] ?? '') ?>" maxlength="120" placeholder="Documento, chave, solicitante ou coautor">
            </label>

            <label class="field styled-field">
                <span>Vinculo</span>
                <select name="escopo">
                    <option value="todas" <?= ($filters['escopo'] ?? 'todas') === 'todas' ? 'selected' : '' ?>><?= $isAdmin ? 'Todos do sistema' : 'Todas' ?></option>
                    <option value="para_mim" <?= ($filters['escopo'] ?? '') === 'para_mim' ? 'selected' : '' ?>>Para minha assinatura</option>
                    <option value="solicitadas" <?= ($filters['escopo'] ?? '') === 'solicitadas' ? 'selected' : '' ?>>Solicitadas por mim</option>
                </select>
            </label>

            <label class="field styled-field">
                <span>Status</span>
                <select name="status">
                    <option value="" <?= ($filters['status'] ?? '') === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="pendente" <?= ($filters['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="autorizado" <?= ($filters['status'] ?? '') === 'autorizado' ? 'selected' : '' ?>>Autorizado</option>
                    <option value="negado" <?= ($filters['status'] ?? '') === 'negado' ? 'selected' : '' ?>>Nao autorizado</option>
                </select>
            </label>

            <label class="field styled-field">
                <span>Documento</span>
                <select name="documento_tipo">
                    <option value="" <?= ($filters['documento_tipo'] ?? '') === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="dti" <?= ($filters['documento_tipo'] ?? '') === 'dti' ? 'selected' : '' ?>>DTI</option>
                    <option value="prestacao_contas" <?= ($filters['documento_tipo'] ?? '') === 'prestacao_contas' ? 'selected' : '' ?>>Prestacao de contas</option>
                    <option value="recomecar" <?= ($filters['documento_tipo'] ?? '') === 'recomecar' ? 'selected' : '' ?>>Programa Recomecar</option>
                </select>
            </label>

            <label class="field styled-field">
                <span>Data inicial</span>
                <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
            </label>

            <label class="field styled-field">
                <span>Data final</span>
                <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
            </label>

            <div class="records-filter-actions signatures-filter-actions">
                <button type="submit" class="primary-button">Filtrar</button>
                <a class="secondary-button" href="<?= h(url('/assinaturas')) ?>">Limpar</a>
            </div>
        </form>

        <?php if ($hasFilters): ?>
            <div class="records-active-filters" aria-label="Filtros ativos">
                <?php if (($filters['busca'] ?? '') !== ''): ?><span>Busca: <?= h($filters['busca']) ?></span><?php endif; ?>
                <?php if (($filters['escopo'] ?? 'todas') !== 'todas'): ?><span><?= h($scopeLabel($filters['escopo'])) ?></span><?php endif; ?>
                <?php if (($filters['status'] ?? '') !== ''): ?><span>Status: <?= h($statusLabel($filters['status'])) ?></span><?php endif; ?>
                <?php if (($filters['documento_tipo'] ?? '') !== ''): ?><span>Documento: <?= h($documentLabel($filters['documento_tipo'])) ?></span><?php endif; ?>
                <?php if (($filters['data_inicio'] ?? '') !== ''): ?><span>Inicio: <?= h($formatDate($filters['data_inicio'])) ?></span><?php endif; ?>
                <?php if (($filters['data_fim'] ?? '') !== ''): ?><span>Fim: <?= h($formatDate($filters['data_fim'])) ?></span><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="table-panel signatures-list-panel">
        <div class="records-list-toolbar">
            <strong><?= h($totalRecords) ?> documento(s)</strong>
            <span>Exibindo <?= h($firstRecord) ?>-<?= h($lastRecord) ?> de <?= h($totalRecords) ?>, maximo de 10 por pagina</span>
        </div>

        <div class="signatures-list">
            <?php foreach (($assinaturas ?? []) as $assinatura): ?>
                <?php
                $isMine = ($assinatura['vinculo'] ?? '') === 'para_mim';
                $isPrincipalHistory = ($assinatura['vinculo'] ?? '') === 'assinante_principal';
                $personLabel = $isAdmin ? 'Solicitante / assinante' : ($isPrincipalHistory ? 'Assinante principal' : ($isMine ? 'Solicitante' : 'Coautor'));
                $personName = $isAdmin
                    ? trim((string) ($assinatura['solicitante_nome'] ?? '-') . ' / ' . (string) ($assinatura['coautor_nome'] ?? '-'))
                    : ($isPrincipalHistory ? ($assinatura['solicitante_nome'] ?? '-') : ($isMine ? ($assinatura['solicitante_nome'] ?? '-') : ($assinatura['coautor_nome'] ?? '-')));
                $personCpf = $isAdmin
                    ? trim((string) ($assinatura['solicitante_cpf'] ?? '') . ' / ' . (string) ($assinatura['coautor_cpf'] ?? ''))
                    : ($isPrincipalHistory ? ($assinatura['solicitante_cpf'] ?? '') : ($isMine ? ($assinatura['solicitante_cpf'] ?? '') : ($assinatura['coautor_cpf'] ?? '')));
                ?>
                <article class="signature-list-card">
                    <div class="signature-list-main">
                        <div class="signature-list-title">
                            <span class="record-protocol"><?= h($documentLabel($assinatura['documento_tipo'] ?? '')) ?></span>
                            <h2><?= h($assinatura['titulo'] ?? '-') ?></h2>
                            <?php if (($assinatura['descricao'] ?? '') !== ''): ?>
                                <p><?= h($assinatura['descricao']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="signature-list-meta">
                            <div>
                                <span>Vinculo</span>
                                <strong><?= h($isAdmin ? 'Controle administrativo' : ($isPrincipalHistory ? 'Assinado por mim' : ($isMine ? 'Para minha assinatura' : 'Solicitada por mim'))) ?></strong>
                            </div>
                            <div>
                                <span><?= h($personLabel) ?></span>
                                <strong><?= h($personName) ?></strong>
                                <?php if ($personCpf !== ''): ?><small><?= h($personCpf) ?></small><?php endif; ?>
                            </div>
                            <div>
                                <span>Assinaturas</span>
                                <strong><?= h((int) ($assinatura['total_assinaturas'] ?? 1)) ?> vinculada(s)</strong>
                                <?php if (!empty($assinatura['coautores_nomes'] ?? '')): ?><small><?= h($assinatura['coautores_nomes']) ?></small><?php endif; ?>
                            </div>
                            <div>
                                <span>Solicitada em</span>
                                <strong><?= h($formatDateTime($assinatura['solicitado_em'] ?? '')) ?></strong>
                            </div>
                            <div>
                                <span>Atualizacao</span>
                                <strong><?= h($formatDateTime($assinatura['atualizado_em'] ?? $assinatura['solicitado_em'] ?? '')) ?></strong>
                            </div>
                        </div>
                    </div>

                    <aside class="signature-list-actions">
                        <span class="status-pill status-<?= h($assinatura['status'] ?? 'pendente') ?>"><?= h($statusLabel($assinatura['status'] ?? '')) ?></span>
                        <a class="secondary-button" href="<?= h(url('/assinaturas/' . (int) $assinatura['id'])) ?>">Detalhes</a>
                        <?php if (!empty($assinatura['url_documento'] ?? '')): ?>
                            <a class="secondary-button" href="<?= h(url((string) $assinatura['url_documento'])) ?>">Ver documento</a>
                        <?php endif; ?>
                    </aside>
                </article>
            <?php endforeach; ?>

            <?php if (($assinaturas ?? []) === []): ?>
                <div class="records-empty-panel signatures-empty-panel">
                    <h2>Nenhuma assinatura encontrada</h2>
                    <p>Ajuste os filtros para localizar documentos pendentes, autorizados ou nao autorizados.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($totalPages > 1): ?>
        <nav class="records-pagination" aria-label="Paginacao das assinaturas">
            <a class="pagination-link <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= h($page > 1 ? $pageUrl($page - 1) : '#') ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Anterior</a>
            <div class="pagination-pages">
                <?php foreach ($visiblePages($page, $totalPages) as $item): ?>
                    <?php if (is_int($item)): ?>
                        <a class="pagination-number <?= $item === $page ? 'is-active' : '' ?>" href="<?= h($pageUrl($item)) ?>" aria-current="<?= $item === $page ? 'page' : 'false' ?>"><?= h($item) ?></a>
                    <?php else: ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <a class="pagination-link <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h($page < $totalPages ? $pageUrl($page + 1) : '#') ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Proxima</a>
        </nav>
    <?php endif; ?>
</section>
