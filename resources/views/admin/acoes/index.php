<?php
$statusLabels = [
    'aberta' => 'Aberta',
    'encerrada' => 'Encerrada',
    'cancelada' => 'Cancelada',
];
$filters = $filters ?? [
    'q' => '',
    'status' => '',
    'municipio_id' => '',
    'municipio_busca' => '',
    'tipo_evento' => '',
    'data_inicio' => '',
    'data_fim' => '',
];
$summary = $summary ?? [];
$pagination = $pagination ?? [
    'page' => 1,
    'per_page' => 10,
    'total' => count($acoes),
    'total_pages' => 1,
];
$isAdmin = (current_user()['perfil'] ?? '') === 'administrador';
$municipioSelecionado = '';
$totalAcoes = (int) ($summary['total_acoes'] ?? $pagination['total'] ?? 0);
$totalAbertas = (int) ($summary['abertas'] ?? 0);
$totalEncerradas = (int) ($summary['encerradas'] ?? 0);
$totalCanceladas = (int) ($summary['canceladas'] ?? 0);
$totalResidencias = (int) ($summary['residencias_cadastradas'] ?? 0);
$totalFamilias = (int) ($summary['familias_cadastradas'] ?? 0);
$ultimaAtualizacao = !empty($summary['ultima_atualizacao'])
    ? strtotime((string) $summary['ultima_atualizacao'])
    : null;
$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 10));
$firstRecord = $totalAcoes > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastRecord = min($totalAcoes, $page * $perPage);

foreach ($municipios ?? [] as $municipio) {
    if ((string) ($filters['municipio_id'] ?? '') === (string) $municipio['id']) {
        $municipioSelecionado = $municipio['nome'] . ' / ' . $municipio['uf'];
        break;
    }
}

$activeFilters = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
unset($activeFilters['municipio_id']);

$filterLabels = [
    'q' => 'Busca',
    'status' => 'Status',
    'municipio_busca' => 'Municipio',
    'tipo_evento' => 'Evento',
    'data_inicio' => 'Inicio',
    'data_fim' => 'Fim',
];

$filterValueLabel = static function (string $key, string $value) use ($statusLabels): string {
    if ($key === 'status') {
        return $statusLabels[$value] ?? $value;
    }

    if (($key === 'data_inicio' || $key === 'data_fim') && strtotime($value) !== false) {
        return date('d/m/Y', strtotime($value));
    }

    return $value;
};

$pageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
    $query['pagina'] = max(1, $targetPage);

    return url('/admin/acoes' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
?>

<section class="records-page actions-page actions-index-page">
    <header class="action-form-header records-header">
        <div>
            <span class="eyebrow">Administracao</span>
            <h1>Acoes emergenciais</h1>
            <p>Gerencie acoes, controle o status de atendimento e compartilhe o cadastro por QR Code somente em acoes abertas.</p>
        </div>
        <a class="primary-link-button" href="<?= h(url('/admin/acoes/novo')) ?>">Nova acao</a>
    </header>

    <section class="records-summary-grid action-summary-grid" aria-label="Resumo das acoes emergenciais">
        <article class="records-summary-card">
            <span>Acoes</span>
            <strong><?= h($totalAcoes) ?></strong>
            <small><?= $activeFilters === [] ? 'Total registrado.' : 'Total encontrado pelos filtros.' ?></small>
        </article>
        <article class="records-summary-card">
            <span>Status</span>
            <strong><?= h($totalAbertas) ?> / <?= h($totalEncerradas) ?> / <?= h($totalCanceladas) ?></strong>
            <small>Abertas / encerradas / canceladas.</small>
        </article>
        <article class="records-summary-card">
            <span>Cadastros</span>
            <strong><?= h($totalResidencias) ?> / <?= h($totalFamilias) ?></strong>
            <small>Residencias / familias vinculadas.</small>
        </article>
        <article class="records-summary-card">
            <span>Ultima acao</span>
            <strong><?= $ultimaAtualizacao !== null ? h(date('d/m/Y', $ultimaAtualizacao)) : '-' ?></strong>
            <small><?= $ultimaAtualizacao !== null ? h(date('H:i', $ultimaAtualizacao)) : 'Sem registros' ?></small>
        </article>
    </section>

    <section class="records-filter-panel action-filter-panel action-filter-modern-panel" aria-label="Filtros de acoes emergenciais">
        <form method="get" action="<?= h(url('/admin/acoes')) ?>" class="action-filter-form action-filter-modern-form">
            <label class="field styled-field action-search-field action-filter-field action-filter-field-wide">
                <span>Buscar</span>
                <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" maxlength="120" list="acoes-busca-list" placeholder="Localidade, evento, municipio, UF ou IBGE">
                <datalist id="acoes-busca-list">
                    <?php foreach ($acoes as $acao): ?>
                        <option value="<?= h($acao['localidade']) ?>"></option>
                        <option value="<?= h($acao['tipo_evento']) ?>"></option>
                        <option value="<?= h($acao['municipio_nome']) ?>"></option>
                    <?php endforeach; ?>
                    <?php foreach ($eventos ?? [] as $evento): ?>
                        <option value="<?= h($evento['tipo_evento']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="field styled-field smart-search-field action-filter-field action-filter-field-wide">
                <span>Municipio</span>
                <input type="search" name="municipio_busca" value="<?= h(($filters['municipio_busca'] ?? '') !== '' ? $filters['municipio_busca'] : $municipioSelecionado) ?>" list="acoes-municipios-list" placeholder="Digite para buscar municipio" data-smart-search data-smart-target="acoes_municipio_id" autocomplete="off">
                <input type="hidden" name="municipio_id" value="<?= h($filters['municipio_id'] ?? '') ?>" data-smart-hidden="acoes_municipio_id">
                <datalist id="acoes-municipios-list">
                    <?php foreach ($municipios ?? [] as $municipio): ?>
                        <option value="<?= h($municipio['nome'] . ' / ' . $municipio['uf']) ?>" data-id="<?= h($municipio['id']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="field styled-field action-filter-field action-filter-field-medium">
                <span>Evento</span>
                <input type="search" name="tipo_evento" value="<?= h($filters['tipo_evento'] ?? '') ?>" maxlength="120" list="acoes-eventos-list" placeholder="Tipo de evento">
                <datalist id="acoes-eventos-list">
                    <?php foreach ($eventos ?? [] as $evento): ?>
                        <option value="<?= h($evento['tipo_evento']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="field styled-field action-filter-field action-filter-field-compact">
                <span>Status</span>
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['status'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field action-filter-field action-filter-field-date">
                <span>Inicio</span>
                <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
            </label>

            <label class="field styled-field action-filter-field action-filter-field-date">
                <span>Fim</span>
                <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
            </label>

            <div class="records-filter-actions action-filter-actions">
                <button type="submit" class="primary-button">Filtrar</button>
                <a class="secondary-button" href="<?= h(url('/admin/acoes')) ?>">Limpar</a>
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
        <span><?= h($totalAcoes) ?> acao(oes) encontrada(s)</span>
        <strong><?= h($firstRecord) ?>-<?= h($lastRecord) ?> de <?= h($totalAcoes) ?></strong>
    </div>

    <?php if ($acoes === []): ?>
        <section class="action-empty-panel records-empty-panel">
            <h2><?= $activeFilters === [] ? 'Nenhuma acao emergencial cadastrada' : 'Nenhuma acao encontrada' ?></h2>
            <p><?= $activeFilters === [] ? 'Crie uma nova acao para liberar o cadastro de residencias e familias.' : 'Revise os filtros aplicados ou limpe a busca para voltar a lista completa.' ?></p>
            <?php if ($activeFilters !== []): ?>
                <a class="primary-link-button" href="<?= h(url('/admin/acoes')) ?>">Limpar filtros</a>
            <?php else: ?>
                <a class="primary-link-button" href="<?= h(url('/admin/acoes/novo')) ?>">Criar primeira acao</a>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="action-card-grid" aria-label="Lista de acoes emergenciais">
            <?php foreach ($acoes as $acao): ?>
                <?php
                $status = (string) $acao['status'];
                $isOpen = $status === 'aberta';
                $activeRecords = (int) ($acao['residencias_cadastradas'] ?? 0);
                $canEdit = $isOpen;
                $canClose = $isOpen && $activeRecords > 0;
                $canCancel = $isOpen && $activeRecords === 0;
                $canActivate = !$isOpen;
                $cadastroUrl = $isOpen ? public_url('/acao/' . $acao['token_publico']) : '';
                $dataEvento = strtotime((string) ($acao['data_evento'] ?? ''));
                $dataCriacao = strtotime((string) ($acao['criado_em'] ?? ''));
                $familias = (int) ($acao['familias_cadastradas'] ?? 0);
                $familiasAtendidas = (int) ($acao['familias_atendidas'] ?? 0);
                $atendimentoPercentual = $familias > 0 ? min(100, (int) round(($familiasAtendidas / $familias) * 100)) : 0;
                ?>
                <article class="action-card action-record-card">
                    <div class="action-card-main">
                        <div class="action-card-title">
                            <span class="status status-<?= h($status) ?>"><?= h($statusLabels[$status] ?? ucfirst($status)) ?></span>
                            <h2><?= h($acao['localidade']) ?></h2>
                            <p><?= h($acao['municipio_nome']) ?> / <?= h($acao['uf']) ?></p>
                        </div>

                        <dl class="action-card-meta">
                            <div>
                                <dt>Evento</dt>
                                <dd><?= h($acao['tipo_evento']) ?></dd>
                            </div>
                            <div>
                                <dt>Data do evento</dt>
                                <dd><?= $dataEvento !== false ? h(date('d/m/Y', $dataEvento)) : '-' ?></dd>
                            </div>
                            <div>
                                <dt>Cadastro</dt>
                                <dd><?= $dataCriacao !== false ? h(date('d/m/Y H:i', $dataCriacao)) : '-' ?></dd>
                            </div>
                            <div>
                                <dt>Residencias</dt>
                                <dd><?= h((int) ($acao['residencias_cadastradas'] ?? 0)) ?></dd>
                            </div>
                            <div>
                                <dt>Familias</dt>
                                <dd><?= h($familias) ?></dd>
                            </div>
                            <div>
                                <dt>Atendidas</dt>
                                <dd><?= h($familiasAtendidas) ?></dd>
                            </div>
                        </dl>

                        <div class="action-qr-state action-qr-state-<?= $isOpen ? 'available' : 'blocked' ?>">
                            <div>
                                <span>Cadastro via QR Code</span>
                                <strong><?= $isOpen ? 'Disponivel' : 'Indisponivel' ?></strong>
                                <small><?= $isOpen ? 'Link liberado para novos cadastros.' : 'Disponivel novamente somente ao ativar a acao.' ?></small>
                            </div>
                            <div class="record-progress" aria-hidden="true">
                                <span style="width: <?= h((string) $atendimentoPercentual) ?>%"></span>
                            </div>
                        </div>
                    </div>

                    <div class="action-card-actions">
                        <?php if ($isOpen): ?>
                            <button
                                type="button"
                                class="secondary-button action-qr-button"
                                data-action-qr-open
                                data-title="<?= h($acao['localidade']) ?>"
                                data-register-url="<?= h($cadastroUrl) ?>"
                            >
                                Link e QR Code
                            </button>
                        <?php else: ?>
                            <span class="action-card-disabled">QR indisponivel</span>
                        <?php endif; ?>

                        <?php if ($canEdit): ?>
                            <a class="secondary-button action-card-button" href="<?= h(url('/admin/acoes/' . $acao['id'] . '/editar')) ?>">Editar</a>
                        <?php else: ?>
                            <span class="action-card-disabled">Edicao bloqueada</span>
                        <?php endif; ?>

                        <?php if ($canActivate): ?>
                            <form method="post" action="<?= h(url('/admin/acoes/' . $acao['id'] . '/ativar')) ?>" class="inline-form js-prevent-double-submit">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.acoes.status.' . $acao['id'] . '.aberta') ?>
                                <button type="submit" class="secondary-button action-card-button" data-loading-text="Ativando...">Ativar</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canClose): ?>
                            <form method="post" action="<?= h(url('/admin/acoes/' . $acao['id'] . '/encerrar')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Encerrar esta acao? Novos cadastros pelo QR Code serao bloqueados.">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.acoes.status.' . $acao['id'] . '.encerrada') ?>
                                <button type="submit" class="secondary-button action-card-button" data-loading-text="Encerrando...">Encerrar</button>
                            </form>
                        <?php elseif ($isOpen): ?>
                            <span class="action-card-disabled">Encerrar indisponivel</span>
                        <?php endif; ?>

                        <?php if ($canCancel): ?>
                            <form method="post" action="<?= h(url('/admin/acoes/' . $acao['id'] . '/cancelar')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Cancelar esta acao? Novos cadastros pelo QR Code serao bloqueados.">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.acoes.status.' . $acao['id'] . '.cancelada') ?>
                                <button type="submit" class="secondary-button action-card-button" data-loading-text="Cancelando...">Cancelar</button>
                            </form>
                        <?php elseif ($isOpen): ?>
                            <span class="action-card-disabled">Cancelar indisponivel</span>
                        <?php endif; ?>

                        <?php if ($isAdmin): ?>
                            <form method="post" action="<?= h(url('/admin/acoes/' . $acao['id'] . '/excluir')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Excluir esta acao da listagem? O registro continuara preservado no banco.">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.acoes.delete.' . $acao['id']) ?>
                                <button type="submit" class="danger-button action-card-button" data-loading-text="Excluindo...">Excluir</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="records-pagination" aria-label="Paginacao de acoes emergenciais">
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

            <a class="pagination-link <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h($page < $totalPages ? $pageUrl($page + 1) : '#') ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Proxima</a>
        </nav>
    <?php endif; ?>
</section>

<dialog class="qr-modal" data-action-qr-modal aria-labelledby="qr-modal-title">
    <form method="dialog" class="qr-modal-close-form">
        <button type="submit" class="qr-modal-close" aria-label="Fechar">x</button>
    </form>
    <div class="qr-modal-content">
        <span class="eyebrow">Aplicativo de cadastro</span>
        <h2 id="qr-modal-title" data-action-qr-title>QR Code da acao</h2>
        <p>Compartilhe este link personalizado ou leia o QR Code para abrir o cadastro web desta acao no computador, celular ou tablet.</p>
        <canvas class="qr-modal-image" data-action-qr-canvas aria-label="QR Code do aplicativo de cadastro"></canvas>
        <label class="qr-modal-link-field">
            <span>Link compartilhavel da acao</span>
            <input type="text" value="" readonly data-action-qr-link>
        </label>
        <p class="qr-modal-rule">Para usuarios com perfil cadastrador, o acesso por este link ou QR Code fica atrelado somente a esta acao aberta. O cadastrador visualiza apenas os proprios registros desta acao e nao acessa dados de outras acoes ou de outros usuarios.</p>
        <span class="qr-modal-copy-status" data-action-qr-copy-status></span>
        <div class="qr-modal-actions">
            <a class="primary-link-button" href="#" target="_blank" rel="noopener" data-action-qr-register>Abrir link da acao</a>
            <button type="button" class="secondary-button" data-action-qr-copy>Copiar link</button>
            <button type="button" class="secondary-button" data-action-qr-share>Compartilhar</button>
        </div>
    </div>
</dialog>
