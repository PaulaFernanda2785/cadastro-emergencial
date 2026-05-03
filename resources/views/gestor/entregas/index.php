<?php
$summary = $summary ?? [];
$filters = $filters ?? [];
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'total' => 0];
$acaoSelecionada = '';
$residenciaSelecionada = '';
$familiaSelecionada = '';
$actionOptionLabel = static function (array $acao): string {
    return trim(
        (string) ($acao['municipio_nome'] ?? '') . '/' . (string) ($acao['uf'] ?? '')
        . ' - ' . (string) ($acao['localidade'] ?? '')
        . ' - ' . (string) ($acao['tipo_evento'] ?? '')
        . ' - Ação #' . (string) ($acao['id'] ?? '')
    );
};
$pageUrl = static function (int $page) use ($filters): string {
    $params = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
    $params['pagina'] = $page;

    return url('/gestor/entregas') . '?' . http_build_query($params);
};

foreach ($acoes ?? [] as $acao) {
    if ((string) ($filters['acao_id'] ?? '') === (string) $acao['id']) {
        $acaoSelecionada = $actionOptionLabel($acao);
        break;
    }
}

foreach ($residencias ?? [] as $residencia) {
    if ((string) ($filters['residencia_id'] ?? '') === (string) $residencia['id']) {
        $residenciaSelecionada = $residencia['protocolo'] . ' - ' . $residencia['bairro_comunidade'];
        break;
    }
}

foreach ($familias ?? [] as $familia) {
    if ((string) ($filters['familia_id'] ?? '') === (string) $familia['id']) {
        $familiaSelecionada = $familia['responsavel_nome'] . ' - ' . $familia['responsavel_cpf'];
        break;
    }
}
require BASE_PATH . '/resources/views/gestor/entregas/_nav.php';
?>

<section class="dashboard-header deliveries-header">
    <div>
        <span class="eyebrow">Gestão operacional</span>
        <h1>Histórico de entregas</h1>
        <p>Consulte entregas registradas por família, ação, residência, tipo de ajuda e período.</p>
    </div>
</section>

<section class="records-summary-grid delivery-summary-grid">
    <article class="records-summary-card">
        <span>Entregas filtradas</span>
        <strong><?= h($summary['total_entregas'] ?? 0) ?></strong>
        <small>Registros encontrados.</small>
    </article>
    <article class="records-summary-card">
        <span>Famílias atendidas</span>
        <strong><?= h($summary['familias_atendidas'] ?? 0) ?></strong>
        <small>Famílias únicas no filtro.</small>
    </article>
    <article class="records-summary-card">
        <span>Quantidade total</span>
        <strong><?= h(number_format((float) ($summary['total_quantidade'] ?? 0), 0, ',', '.')) ?></strong>
        <small>Soma das quantidades.</small>
    </article>
    <article class="records-summary-card">
        <span>Última entrega</span>
        <strong><?= !empty($summary['ultima_entrega']) ? h(date('d/m/Y', strtotime((string) $summary['ultima_entrega']))) : '-' ?></strong>
        <small><?= !empty($summary['ultima_entrega']) ? h(date('H:i', strtotime((string) $summary['ultima_entrega']))) : 'Sem registro' ?></small>
    </article>
</section>

<section class="records-filter-panel delivery-filter-panel delivery-history-filter-panel">
    <div class="table-heading">
        <h2>Filtros inteligentes</h2>
        <span>Combine texto, ação, residência, período e tipo de ajuda.</span>
    </div>
    <form method="get" action="<?= h(url('/gestor/entregas')) ?>" class="delivery-filter-form delivery-history-filter-form">
        <label class="field styled-field delivery-history-filter-field delivery-history-filter-field-wide">
            <span>Buscar</span>
            <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" list="historico-busca-list" placeholder="Nome, CPF, comprovante, protocolo">
            <datalist id="historico-busca-list">
                <?php foreach ($familias ?? [] as $familia): ?>
                    <option value="<?= h($familia['responsavel_nome']) ?>"></option>
                    <option value="<?= h($familia['responsavel_cpf']) ?>"></option>
                    <option value="<?= h($familia['protocolo']) ?>"></option>
                <?php endforeach; ?>
                <?php foreach ($acoes ?? [] as $acao): ?>
                    <option value="<?= h($actionOptionLabel($acao)) ?>"></option>
                    <option value="<?= h($acao['municipio_nome'] . '/' . $acao['uf']) ?>"></option>
                    <option value="<?= h($acao['localidade']) ?>"></option>
                    <option value="<?= h($acao['tipo_evento']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </label>
        <label class="field styled-field smart-search-field delivery-history-filter-field delivery-history-filter-field-wide">
            <span>Ação</span>
            <input type="search" name="acao_busca" value="<?= h(($filters['acao_busca'] ?? '') !== '' ? $filters['acao_busca'] : $acaoSelecionada) ?>" list="historico-acoes-list" placeholder="Digite para buscar a ação" data-smart-search data-smart-target="historico_acao_id" autocomplete="off">
            <input type="hidden" name="acao_id" value="<?= h($filters['acao_id'] ?? '') ?>" data-smart-hidden="historico_acao_id">
            <datalist id="historico-acoes-list">
                <?php foreach ($acoes ?? [] as $acao): ?>
                    <option value="<?= h($actionOptionLabel($acao)) ?>" data-id="<?= h($acao['id']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </label>
        <label class="field styled-field smart-search-field delivery-history-filter-field delivery-history-filter-field-medium">
            <span>Residência</span>
            <input type="search" name="residencia_busca" value="<?= h(($filters['residencia_busca'] ?? '') !== '' ? $filters['residencia_busca'] : $residenciaSelecionada) ?>" list="historico-residencias-list" placeholder="Digite protocolo ou bairro" data-smart-search data-smart-target="historico_residencia_id" autocomplete="off">
            <input type="hidden" name="residencia_id" value="<?= h($filters['residencia_id'] ?? '') ?>" data-smart-hidden="historico_residencia_id">
            <datalist id="historico-residencias-list">
                <?php foreach ($residencias ?? [] as $residencia): ?>
                    <option value="<?= h($residencia['protocolo'] . ' - ' . $residencia['bairro_comunidade']) ?>" data-id="<?= h($residencia['id']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </label>
        <label class="field styled-field smart-search-field delivery-history-filter-field delivery-history-filter-field-medium">
            <span>Família</span>
            <input type="search" name="familia_busca" value="<?= h(($filters['familia_busca'] ?? '') !== '' ? $filters['familia_busca'] : $familiaSelecionada) ?>" list="historico-familias-list" placeholder="Digite nome ou CPF" data-smart-search data-smart-target="historico_familia_id" autocomplete="off">
            <input type="hidden" name="familia_id" value="<?= h($filters['familia_id'] ?? '') ?>" data-smart-hidden="historico_familia_id">
            <datalist id="historico-familias-list">
                <?php foreach ($familias ?? [] as $familia): ?>
                    <option value="<?= h($familia['responsavel_nome'] . ' - ' . $familia['responsavel_cpf']) ?>" data-id="<?= h($familia['id']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </label>
        <label class="field styled-field delivery-history-filter-field delivery-history-filter-field-compact">
            <span>Tipo de ajuda</span>
            <select name="tipo_ajuda_id">
                <option value="">Todos</option>
                <?php foreach ($tipos ?? [] as $tipo): ?>
                    <option value="<?= h($tipo['id']) ?>" <?= (string) ($filters['tipo_ajuda_id'] ?? '') === (string) $tipo['id'] ? 'selected' : '' ?>>
                        <?= h($tipo['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field styled-field delivery-history-filter-field delivery-history-filter-field-compact">
            <span>Status da entrega</span>
            <select name="status_entrega">
                <option value="">Todos</option>
                <option value="entregue" <?= ($filters['status_entrega'] ?? '') === 'entregue' ? 'selected' : '' ?>>Com entrega</option>
                <option value="nao_entregue" <?= ($filters['status_entrega'] ?? '') === 'nao_entregue' ? 'selected' : '' ?>>Sem entrega</option>
            </select>
        </label>
        <label class="field styled-field delivery-history-filter-field delivery-history-filter-field-date">
            <span>Início</span>
            <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
        </label>
        <label class="field styled-field delivery-history-filter-field delivery-history-filter-field-date">
            <span>Fim</span>
            <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
        </label>
        <div class="records-filter-actions delivery-history-filter-actions">
            <button type="submit" class="primary-button">Filtrar</button>
            <a class="secondary-button" href="<?= h(url('/gestor/entregas')) ?>">Limpar</a>
        </div>
    </form>
</section>

<section class="table-panel delivery-table-panel">
    <div class="table-heading">
        <h2>Registros de entrega</h2>
        <span><?= h($pagination['total'] ?? count($entregas)) ?> comprovante(s)</span>
    </div>
    <?php if ($entregas === []): ?>
        <div class="empty-state">Nenhuma entrega encontrada para os filtros informados.</div>
    <?php else: ?>
        <div class="delivery-record-list">
            <?php foreach ($entregas as $entrega): ?>
                <article class="delivery-record-card">
                    <div class="delivery-record-code">
                        <span class="eyebrow">Comprovante</span>
                        <strong><?= h($entrega['comprovante_codigo']) ?></strong>
                    </div>
                    <div class="delivery-record-family">
                        <span class="eyebrow">Família</span>
                        <strong><?= h($entrega['responsavel_nome']) ?></strong>
                        <small><?= h($entrega['responsavel_cpf']) ?></small>
                    </div>
                    <div class="delivery-record-items">
                        <span class="eyebrow">Ajuda</span>
                        <p><?= h($entrega['itens_resumo'] ?? $entrega['tipo_ajuda_nome'] ?? '-') ?></p>
                    </div>
                    <div class="delivery-record-meta">
                        <span>
                            <small>Quantidade</small>
                            <strong><?= h(number_format((float) ($entrega['quantidade_total'] ?? $entrega['quantidade'] ?? 0), 0, ',', '.')) ?></strong>
                        </span>
                        <span>
                            <small>Residência</small>
                            <strong><a href="<?= h(url('/cadastros/residencias/' . $entrega['residencia_id'])) ?>"><?= h($entrega['protocolo']) ?></a></strong>
                            <em><?= h($entrega['bairro_comunidade']) ?> - <?= h($entrega['municipio_nome']) ?>/<?= h($entrega['uf']) ?></em>
                        </span>
                        <span>
                            <small>Entrega</small>
                            <strong><?= h(date('d/m/Y H:i', strtotime((string) $entrega['data_entrega']))) ?></strong>
                            <em><?= h($entrega['entregue_por_nome']) ?></em>
                        </span>
                    </div>
                    <a class="secondary-button delivery-record-action" href="<?= h(url('/gestor/entregas/' . $entrega['id'] . '/comprovante')) ?>">Comprovante</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (($pagination['pages'] ?? 1) > 1): ?>
        <nav class="records-pagination delivery-pagination" aria-label="Paginação do histórico de entregas">
            <a class="secondary-button <?= (int) $pagination['page'] <= 1 ? 'is-disabled' : '' ?>" href="<?= h($pageUrl(max(1, (int) $pagination['page'] - 1))) ?>">Anterior</a>
            <div class="pagination-pages">
                <?php
                $currentPage = (int) $pagination['page'];
                $totalPages = (int) $pagination['pages'];
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                if ($endPage - $startPage < 4) {
                    $startPage = max(1, min($startPage, $endPage - 4));
                    $endPage = min($totalPages, max($endPage, $startPage + 4));
                }
                ?>
                <?php if ($startPage > 1): ?>
                    <a href="<?= h($pageUrl(1)) ?>">1</a>
                    <?php if ($startPage > 2): ?><span>...</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                    <a class="<?= $page === (int) $pagination['page'] ? 'is-active' : '' ?>" href="<?= h($pageUrl($page)) ?>"><?= h($page) ?></a>
                <?php endfor; ?>
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?><span>...</span><?php endif; ?>
                    <a href="<?= h($pageUrl($totalPages)) ?>"><?= h($totalPages) ?></a>
                <?php endif; ?>
            </div>
            <a class="secondary-button <?= (int) $pagination['page'] >= (int) $pagination['pages'] ? 'is-disabled' : '' ?>" href="<?= h($pageUrl(min((int) $pagination['pages'], (int) $pagination['page'] + 1))) ?>">Próxima</a>
        </nav>
    <?php endif; ?>
</section>
