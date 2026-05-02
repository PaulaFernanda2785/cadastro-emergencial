<?php
$filters = $filters ?? [
    'q' => '',
    'acao_id' => '',
    'acao_busca' => '',
    'residencia_id' => '',
    'residencia_busca' => '',
    'situacao' => '',
    'entregas' => '',
    'cadastro' => '',
    'data_inicio' => '',
    'data_fim' => '',
];
$summary = $summary ?? [];
$pagination = $pagination ?? [
    'page' => 1,
    'per_page' => 10,
    'total' => count($familias),
    'total_pages' => 1,
];
$acaoSelecionada = '';
$residenciaSelecionada = '';
$totalFamilias = (int) ($summary['total_familias'] ?? $pagination['total'] ?? 0);
$totalIntegrantes = (int) ($summary['total_integrantes'] ?? 0);
$comEntrega = (int) ($summary['com_entrega'] ?? 0);
$semEntrega = max(0, $totalFamilias - $comEntrega);
$cadastroConcluido = (int) ($summary['cadastro_concluido'] ?? 0);
$cadastroPendente = max(0, $totalFamilias - $cadastroConcluido);
$ultimaAtualizacao = !empty($summary['ultima_atualizacao'])
    ? strtotime((string) $summary['ultima_atualizacao'])
    : null;
$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 10));
$firstRecord = $totalFamilias > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastRecord = min($totalFamilias, $page * $perPage);
$situacaoOptions = [
    'desabrigado' => 'Desabrigado',
    'desalojado' => 'Desalojado',
    'aluguel_social' => 'Aluguel social',
    'permanece_residencia' => 'Permanece na residencia',
];
$entregaOptions = [
    'com_entrega' => 'Com entrega',
    'sem_entrega' => 'Sem entrega',
];
$cadastroOptions = [
    'concluido' => 'Concluido',
    'pendente' => 'Pendente',
];
$filterLabels = [
    'q' => 'Busca',
    'acao_busca' => 'Acao',
    'residencia_busca' => 'Residencia',
    'situacao' => 'Situacao',
    'entregas' => 'Entregas',
    'cadastro' => 'Cadastro',
    'data_inicio' => 'Inicio',
    'data_fim' => 'Fim',
];

foreach ($acoes ?? [] as $acao) {
    if ((string) ($filters['acao_id'] ?? '') === (string) $acao['id']) {
        $acaoSelecionada = $acao['localidade'] . ' - ' . $acao['tipo_evento'];
        break;
    }
}

foreach ($residencias ?? [] as $residencia) {
    if ((string) ($filters['residencia_id'] ?? '') === (string) $residencia['id']) {
        $residenciaSelecionada = $residencia['protocolo'] . ' - ' . $residencia['bairro_comunidade'];
        break;
    }
}

$activeFilters = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
unset($activeFilters['acao_id'], $activeFilters['residencia_id']);

$filterValueLabel = static function (string $key, string $value) use ($situacaoOptions, $entregaOptions, $cadastroOptions): string {
    if ($key === 'situacao') {
        return $situacaoOptions[$value] ?? $value;
    }

    if ($key === 'entregas') {
        return $entregaOptions[$value] ?? $value;
    }

    if ($key === 'cadastro') {
        return $cadastroOptions[$value] ?? $value;
    }

    if (($key === 'data_inicio' || $key === 'data_fim') && strtotime($value) !== false) {
        return date('d/m/Y', strtotime($value));
    }

    return $value;
};

$pageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
    $query['pagina'] = max(1, $targetPage);

    return url('/gestor/familias' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
$canRegisterDelivery = in_array((string) (current_user()['perfil'] ?? ''), ['gestor', 'administrador'], true);
?>

<section class="records-page family-index-page">
    <header class="action-form-header records-header">
        <div>
            <span class="eyebrow">Cadastro emergencial</span>
            <h1>Familias cadastradas</h1>
            <p>Consulta operacional das familias vinculadas as residencias, com filtros por acao, residencia, status e periodo.</p>
        </div>
    </header>

    <section class="records-summary-grid family-summary-grid" aria-label="Resumo das familias">
        <article class="records-summary-card">
            <span>Familias</span>
            <strong><?= h($totalFamilias) ?></strong>
            <small><?= $activeFilters === [] ? 'Total registrado no escopo atual.' : 'Total encontrado pelos filtros.' ?></small>
        </article>
        <article class="records-summary-card">
            <span>Integrantes</span>
            <strong><?= h($totalIntegrantes) ?></strong>
            <small>Pessoas informadas nos cadastros filtrados.</small>
        </article>
        <article class="records-summary-card">
            <span>Entregas</span>
            <strong><?= h($comEntrega) ?> / <?= h($semEntrega) ?></strong>
            <small>Com entrega / sem entrega registrada.</small>
        </article>
        <article class="records-summary-card">
            <span>Revisao</span>
            <strong><?= h($cadastroConcluido) ?> / <?= h($cadastroPendente) ?></strong>
            <small>Concluido / pendente de revisao.</small>
        </article>
    </section>

    <section class="records-filter-panel family-filter-panel" aria-label="Filtros de familias">
        <form method="get" action="<?= h(url('/gestor/familias')) ?>" class="family-filter-form">
            <label class="field styled-field family-search-field">
                <span>Buscar</span>
                <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" maxlength="120" list="familias-busca-list" placeholder="Nome, CPF, telefone, protocolo, bairro ou municipio">
                <datalist id="familias-busca-list">
                    <?php foreach ($familias as $familia): ?>
                        <option value="<?= h($familia['responsavel_nome']) ?>"></option>
                        <option value="<?= h($familia['responsavel_cpf']) ?>"></option>
                        <option value="<?= h($familia['protocolo']) ?>"></option>
                    <?php endforeach; ?>
                    <?php foreach ($acoes ?? [] as $acao): ?>
                        <option value="<?= h($acao['localidade']) ?>"></option>
                        <option value="<?= h($acao['tipo_evento']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="field styled-field smart-search-field">
                <span>Acao</span>
                <input type="search" name="acao_busca" value="<?= h(($filters['acao_busca'] ?? '') !== '' ? $filters['acao_busca'] : $acaoSelecionada) ?>" list="familias-acoes-list" placeholder="Digite para buscar a acao" data-smart-search data-smart-target="familias_acao_id" autocomplete="off">
                <input type="hidden" name="acao_id" value="<?= h($filters['acao_id'] ?? '') ?>" data-smart-hidden="familias_acao_id">
                <datalist id="familias-acoes-list">
                    <?php foreach ($acoes ?? [] as $acao): ?>
                        <option value="<?= h($acao['localidade'] . ' - ' . $acao['tipo_evento']) ?>" data-id="<?= h($acao['id']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="field styled-field smart-search-field">
                <span>Residencia</span>
                <input type="search" name="residencia_busca" value="<?= h(($filters['residencia_busca'] ?? '') !== '' ? $filters['residencia_busca'] : $residenciaSelecionada) ?>" list="familias-residencias-list" placeholder="Digite protocolo ou bairro" data-smart-search data-smart-target="familias_residencia_id" autocomplete="off">
                <input type="hidden" name="residencia_id" value="<?= h($filters['residencia_id'] ?? '') ?>" data-smart-hidden="familias_residencia_id">
                <datalist id="familias-residencias-list">
                    <?php foreach ($residencias ?? [] as $residencia): ?>
                        <option value="<?= h($residencia['protocolo'] . ' - ' . $residencia['bairro_comunidade']) ?>" data-id="<?= h($residencia['id']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="field styled-field">
                <span>Situacao</span>
                <select name="situacao">
                    <option value="">Todas</option>
                    <?php foreach ($situacaoOptions as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['situacao'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field">
                <span>Entregas</span>
                <select name="entregas">
                    <option value="">Todas</option>
                    <?php foreach ($entregaOptions as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['entregas'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field">
                <span>Cadastro</span>
                <select name="cadastro">
                    <option value="">Todos</option>
                    <?php foreach ($cadastroOptions as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['cadastro'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field">
                <span>Inicio</span>
                <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
            </label>

            <label class="field styled-field">
                <span>Fim</span>
                <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
            </label>

            <div class="records-filter-actions family-filter-actions">
                <button type="submit" class="primary-button">Filtrar</button>
                <a class="secondary-button" href="<?= h(url('/gestor/familias')) ?>">Limpar</a>
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
        <span><?= h($totalFamilias) ?> familia(s) encontrada(s)</span>
        <strong><?= h($firstRecord) ?>-<?= h($lastRecord) ?> de <?= h($totalFamilias) ?></strong>
    </div>

    <?php if ($familias === []): ?>
        <section class="action-empty-panel records-empty-panel">
            <h2><?= $activeFilters === [] ? 'Nenhuma familia cadastrada' : 'Nenhuma familia encontrada' ?></h2>
            <p><?= $activeFilters === [] ? 'Quando uma familia for vinculada a uma residencia cadastrada, ela aparecera aqui.' : 'Revise os filtros aplicados ou limpe a busca para voltar a lista completa.' ?></p>
            <?php if ($activeFilters !== []): ?>
                <a class="primary-link-button" href="<?= h(url('/gestor/familias')) ?>">Limpar filtros</a>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="family-card-list" aria-label="Lista de familias cadastradas">
            <?php foreach ($familias as $familia): ?>
                <?php
                $entregasRegistradas = (int) ($familia['entregas_registradas'] ?? 0);
                $cadastroOk = (int) ($familia['cadastro_concluido'] ?? 0) === 1;
                $situacaoKey = (string) ($familia['situacao_familia'] ?? '');
                $situacaoClass = $situacaoKey !== '' ? preg_replace('/[^a-z0-9_-]+/i', '-', $situacaoKey) : 'sem-situacao';
                $dataCadastro = strtotime((string) ($familia['criado_em'] ?? ''));
                ?>
                <article class="family-index-card">
                    <div class="family-index-main">
                        <div class="family-index-title">
                            <span class="record-protocol"><?= h(familia_comprovante_codigo($familia)) ?></span>
                            <h2><?= h($familia['responsavel_nome']) ?></h2>
                            <p><?= h($familia['responsavel_cpf']) ?><?= !empty($familia['telefone']) ? ' - ' . h($familia['telefone']) : '' ?></p>
                        </div>

                        <div class="family-index-statuses" aria-label="Status da familia">
                            <span class="family-status-pill family-status-<?= $entregasRegistradas > 0 ? 'delivered' : 'pending' ?>">
                                <?= $entregasRegistradas > 0 ? h($entregasRegistradas . ' entrega(s)') : 'Sem entrega' ?>
                            </span>
                            <span class="family-status-pill family-status-<?= $cadastroOk ? 'reviewed' : 'open' ?>">
                                <?= $cadastroOk ? 'Cadastro concluido' : 'Revisao pendente' ?>
                            </span>
                            <span class="family-status-pill family-status-situacao family-status-<?= h($situacaoClass) ?>">
                                <?= h(familia_situacao_label($familia['situacao_familia'] ?? null)) ?>
                            </span>
                        </div>

                        <dl class="family-index-meta">
                            <div>
                                <dt>Integrantes</dt>
                                <dd><?= h((int) ($familia['quantidade_integrantes'] ?? 0)) ?></dd>
                            </div>
                            <div>
                                <dt>Renda</dt>
                                <dd><?= h(familia_renda_label($familia['renda_familiar'] ?? null)) ?></dd>
                            </div>
                            <div>
                                <dt>Residencia</dt>
                                <dd><a href="<?= h(url('/cadastros/residencias/' . $familia['residencia_id'])) ?>"><?= h($familia['protocolo']) ?></a></dd>
                            </div>
                            <div>
                                <dt>Localidade</dt>
                                <dd><?= h($familia['bairro_comunidade']) ?> - <?= h($familia['municipio_nome']) ?>/<?= h($familia['uf']) ?></dd>
                            </div>
                            <div>
                                <dt>Acao</dt>
                                <dd><?= h($familia['localidade']) ?> - <?= h($familia['tipo_evento']) ?></dd>
                            </div>
                            <div>
                                <dt>Cadastrado</dt>
                                <dd><?= $dataCadastro !== false ? h(date('d/m/Y H:i', $dataCadastro)) : '-' ?></dd>
                            </div>
                        </dl>

                        <?php if ($entregasRegistradas > 0): ?>
                            <div class="family-delivery-summary">
                                <span>Itens ja entregues</span>
                                <strong><?= h($familia['entregas_itens_resumo'] ?: 'Entrega registrada') ?></strong>
                                <?php if (!empty($familia['ultima_entrega'])): ?>
                                    <small>Ultima entrega em <?= h(date('d/m/Y H:i', strtotime((string) $familia['ultima_entrega']))) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <aside class="family-index-actions" aria-label="Acoes da familia">
                        <a class="primary-link-button" href="<?= h(url('/cadastros/residencias/' . $familia['residencia_id'] . '/familias/' . $familia['id'])) ?>">Ver detalhe</a>
                        <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $familia['residencia_id'] . '/familias/' . $familia['id'] . '/comprovante')) ?>">Comprovante</a>
                        <?php if ($canRegisterDelivery): ?>
                            <a class="secondary-button" href="<?= h(url('/gestor/familias/' . $familia['id'] . '/entregas/novo')) ?>">Entrega</a>
                        <?php endif; ?>
                    </aside>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="records-pagination" aria-label="Paginacao de familias">
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
