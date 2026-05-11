<?php
$filters = $filters ?? [];
$summary = $summary ?? [];
$records = $records ?? [];
$documentsByRecord = $documentsByRecord ?? [];
$historyByRecord = $historyByRecord ?? [];
$analysisUsers = $analysisUsers ?? [];
$assignmentSummary = is_array($assignmentSummary ?? null) ? $assignmentSummary : [];
$userQueueStatus = is_array($userQueueStatus ?? null) ? $userQueueStatus : [];
$managedQueueStatus = is_array($managedQueueStatus ?? null) ? $managedQueueStatus : [];
$distributionHistory = is_array($distributionHistory ?? null) ? $distributionHistory : [];
$canManageAssignments = (bool) ($canManageAssignments ?? false);
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];
$openEditId = (int) ($openEditId ?? 0);
$analysisValidation = flash('recomecar_analysis_validation', []);
$analysisValidation = is_array($analysisValidation) ? $analysisValidation : [];
$analysisValidationMessages = array_values(array_filter(
    $analysisValidation['messages'] ?? [],
    static fn (mixed $message): bool => trim((string) $message) !== ''
));
$analysisValidationFamiliaId = (int) ($analysisValidation['familia_id'] ?? 0);
$analysisOldInput = flash('recomecar_analysis_old_input', []);
$analysisOldInput = is_array($analysisOldInput) ? $analysisOldInput : [];
$pagination = $pagination ?? ['page' => 1, 'total_pages' => 1, 'total' => 0, 'per_page' => 8];
$hasAppliedFilters = (bool) ($hasAppliedFilters ?? false);
$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 8));
$totalRecords = (int) ($pagination['total'] ?? 0);

$valueOrDash = static function (mixed $value): string {
    $text = trim((string) $value);

    return $text !== '' ? $text : '-';
};

$dateValue = static function (mixed $value): string {
    $text = trim((string) $value);

    return preg_match('/^\d{4}-\d{2}-\d{2}/', $text) === 1 ? substr($text, 0, 10) : '';
};

$dateLabel = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '-';
};

$dateOnlyLabel = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp !== false ? date('d/m/Y', $timestamp) : '-';
};

$strategyLabel = static function (mixed $value): string {
    return match ((string) $value) {
        'pares_impares' => 'Registros pares e ímpares',
        'blocos' => 'Blocos sequenciais',
        default => 'Estratégia personalizada',
    };
};

$actionOptionLabel = static function (array $acao): string {
    return trim(
        (string) ($acao['municipio_nome'] ?? '') . '/' . (string) ($acao['uf'] ?? '')
        . ' - ' . (string) ($acao['localidade'] ?? '')
        . ' - ' . (string) ($acao['tipo_evento'] ?? '')
        . ' - Ação #' . (string) ($acao['id'] ?? '')
    );
};

$selectedActionLabel = '';
foreach ($acoes ?? [] as $acao) {
    if ((string) ($filters['acao_id'] ?? '') === (string) ($acao['id'] ?? '')) {
        $selectedActionLabel = $actionOptionLabel($acao);
        break;
    }
}

$selectedAnalystLabel = '';
foreach ($analysisUsers as $analysisUser) {
    if ((string) ($filters['analista_id'] ?? '') === (string) ($analysisUser['id'] ?? '')) {
        $selectedAnalystLabel = trim(
            (string) ($analysisUser['nome'] ?? '')
            . (($analysisUser['perfil'] ?? '') !== '' ? ' - ' . (string) $analysisUser['perfil'] : '')
        );
        break;
    }
}

$pageUrl = static function (int $targetPage) use ($filters): string {
    $params = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
    $params['pagina'] = max(1, $targetPage);

    return url('/gestor/recomecar/analise') . '?' . http_build_query($params);
};

$renderFilterFields = static function (array $filters, int $page, array $except = []): void {
    $except = array_fill_keys($except, true);
    foreach ($filters as $key => $value) {
        if (isset($except[$key])) {
            continue;
        }

        if ((string) $value === '') {
            continue;
        }
        echo '<input type="hidden" name="filters[' . h((string) $key) . ']" value="' . h((string) $value) . '">' . PHP_EOL;
    }
    echo '<input type="hidden" name="filters[pagina]" value="' . h((string) $page) . '">' . PHP_EOL;
};

$displaySequenceByFamilyId = [];
foreach ($records as $recordIndex => $record) {
    $recordFamilyId = (int) ($record['familia_id'] ?? 0);
    if ($recordFamilyId > 0) {
        $displaySequenceByFamilyId[$recordFamilyId] = (($page - 1) * $perPage) + $recordIndex + 1;
    }
}

$recordsByAptitude = [
    'apta' => array_values(array_filter($records, static fn (array $record): bool => (string) ($record['aptidao'] ?? '') === 'apta')),
    'inapta' => array_values(array_filter($records, static fn (array $record): bool => (string) ($record['aptidao'] ?? '') !== 'apta')),
];
$aptitudeSections = [
    'apta' => 'Famílias aptas',
    'inapta' => 'Famílias inaptas',
];

if (($filters['aptidao'] ?? 'todas') === 'apta') {
    $aptitudeSections = ['apta' => 'Famílias aptas'];
} elseif (($filters['aptidao'] ?? 'todas') === 'inapta') {
    $aptitudeSections = ['inapta' => 'Famílias inaptas'];
}

$field = static function (array $record, string $name) use ($valueOrDash): string {
    return $valueOrDash($record[$name] ?? '');
};

$documentUrl = static function (array $record, array $document): string {
    $residenciaId = (int) ($record['residencia_id'] ?? 0);
    $familiaId = (int) ($record['familia_id'] ?? 0);
    $documentId = (int) ($document['id'] ?? 0);

    if ((int) ($document['familia_id'] ?? 0) > 0) {
        return url('/cadastros/residencias/' . $residenciaId . '/familias/' . $familiaId . '/documentos/' . $documentId);
    }

    return url('/cadastros/residencias/' . $residenciaId . '/documentos/' . $documentId);
};

$formatBytes = static function (mixed $bytes): string {
    $value = (int) $bytes;
    if ($value <= 0) {
        return '-';
    }

    return $value >= 1048576 ? number_format($value / 1048576, 1, ',', '.') . ' MB' : number_format($value / 1024, 1, ',', '.') . ' KB';
};

$decodeHistory = static function (array $log): array {
    $decoded = json_decode((string) ($log['descricao'] ?? ''), true);

    return is_array($decoded) ? $decoded : [];
};

$isBlank = static fn (mixed $value): bool => trim((string) $value) === '';

$cpfDigits = static fn (mixed $value): string => preg_replace('/\D+/', '', (string) $value) ?? '';

$pendingFieldsForRecord = static function (array $record) use ($isBlank, $cpfDigits): array {
    $pending = [];
    $required = [
        'bairro_comunidade' => 'Bairro/comunidade',
        'endereco' => 'Endereço',
        'imovel' => 'Imóvel',
        'condicao_residencia' => 'Condição da residência',
        'quantidade_familias' => 'Qtd. famílias',
        'responsavel_nome' => 'Responsável',
        'quantidade_integrantes' => 'Qtd. integrantes',
        'renda_familiar' => 'Renda familiar',
        'situacao_familia' => 'Situação',
    ];

    foreach ($required as $fieldName => $label) {
        if ($isBlank($record[$fieldName] ?? '')) {
            $pending[$fieldName] = $label;
        }
    }

    if ($isBlank($record['latitude'] ?? '') || $isBlank($record['longitude'] ?? '')) {
        $pending['latitude'] = 'Latitude';
        $pending['longitude'] = 'Longitude';
        $pending['coordenadas'] = 'Ponto no mapa';
    }

    $responsavelCpf = $cpfDigits($record['responsavel_cpf'] ?? '');
    if ($responsavelCpf === '' || strlen($responsavelCpf) !== 11) {
        $pending['responsavel_cpf'] = 'CPF do responsável';
    }

    $email = trim((string) ($record['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $pending['email'] = 'E-mail do responsável inválido';
    }

    if (!empty($record['recebe_beneficio_social']) && $isBlank($record['beneficio_social_nome'] ?? '')) {
        $pending['beneficio_social_nome'] = 'Nome do benefício';
    }

    return $pending;
};

$pendingClass = static fn (array $pendingFields, string $fieldName): string => isset($pendingFields[$fieldName]) ? ' is-analysis-pending-field' : '';
?>

<section class="records-page recomecar-analysis-page">
    <header class="dashboard-header deliveries-header accountability-header no-print">
        <div>
            <span class="eyebrow">Programa Recomeçar</span>
            <h1>Análise operacional</h1>
            <p>Confira famílias registradas, ajuste dados permitidos, consulte documentos e libere a família analisada para a entrega.</p>
        </div>
        <div class="recomecar-analysis-header-actions">
            <button type="button" class="secondary-button" data-history-back data-history-fallback="<?= h(url('/gestor/recomecar')) ?>">Voltar</button>
            <a class="secondary-button" href="<?= h(url('/gestor/recomecar')) ?>">Documento oficial</a>
        </div>
    </header>

    <section class="records-summary-grid delivery-summary-grid no-print">
        <article class="records-summary-card">
            <span>Registros no recorte</span>
            <strong><?= h($summary['total_familias'] ?? 0) ?></strong>
            <small>Famílias vinculadas ao Recomeçar.</small>
        </article>
        <article class="records-summary-card">
            <span>Aptas</span>
            <strong><?= h($summary['familias_aptas'] ?? 0) ?></strong>
            <small>Conforme regra atual do sistema.</small>
        </article>
        <article class="records-summary-card">
            <span>Inaptas</span>
            <strong><?= h($summary['familias_inaptas'] ?? 0) ?></strong>
            <small>Renda ou condição do imóvel bloqueia.</small>
        </article>
        <article class="records-summary-card">
            <span>Pendentes de análise</span>
            <strong><?= h($summary['familias_pendentes'] ?? 0) ?></strong>
            <small>Sem liberação registrada.</small>
        </article>
        <article class="records-summary-card">
            <span>Analisadas</span>
            <strong><?= h($summary['familias_analisadas'] ?? 0) ?></strong>
            <small>Liberadas para entrega.</small>
        </article>
    </section>

    <?php if ((int) ($userQueueStatus['total'] ?? 0) > 0 && (int) ($userQueueStatus['abertas'] ?? 0) > 0): ?>
        <section class="recomecar-user-queue-panel no-print">
            <div>
                <span class="eyebrow">Minha distribuição</span>
                <h2>Fila de análise atribuída</h2>
                <p>
                    <?= h((int) ($userQueueStatus['total'] ?? 0)) ?> registro(s) distribuido(s)
                    <?php if (($userQueueStatus['distribuidor_nome'] ?? '') !== ''): ?>
                        por <?= h($userQueueStatus['distribuidor_nome']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="recomecar-user-queue-metrics">
                <span><strong><?= h((int) ($userQueueStatus['total'] ?? 0)) ?></strong>Total</span>
                <span><strong><?= h((int) ($userQueueStatus['analisadas'] ?? 0)) ?></strong>Analisadas</span>
                <span><strong><?= h((int) ($userQueueStatus['pendentes'] ?? 0)) ?></strong>Pendentes</span>
            </div>
            <form method="post" action="<?= h(url('/gestor/recomecar/analise/concluir-fila')) ?>" class="js-prevent-double-submit recomecar-user-queue-action">
                <?= csrf_field() ?>
                <?= idempotency_field('gestor.recomecar.analysis.queue.complete.' . (int) ($currentUser['id'] ?? 0)) ?>
                <button type="submit" class="primary-button" data-loading-text="Concluindo..." <?= (int) ($userQueueStatus['pendentes'] ?? 0) > 0 ? 'disabled' : '' ?>>Análise concluída</button>
                <small><?= (int) ($userQueueStatus['pendentes'] ?? 0) > 0 ? 'Conclua todos os registros para liberar esta ação.' : 'Ao concluir, esta distribuição deixa de aparecer para você.' ?></small>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($canManageAssignments): ?>
        <section class="recomecar-assignment-panel no-print">
            <div class="recomecar-assignment-head">
                <div>
                    <span class="eyebrow">Distribuição de análise</span>
                    <h2>Selecionar analistas e dividir registros</h2>
                    <p>Selecione primeiro os analistas, depois informe a ação, o período de cadastro e a forma de distribuição.</p>
                </div>
                <div class="recomecar-assignment-metrics">
                    <span><?= h((int) ($assignmentSummary['atribuidas'] ?? 0)) ?> atribuidas</span>
                    <span><?= h((int) ($assignmentSummary['sem_atribuicao'] ?? 0)) ?> sem analista</span>
                    <span><?= h((int) ($assignmentSummary['analistas_com_registros'] ?? 0)) ?> analista(s)</span>
                </div>
            </div>

            <div class="recomecar-assignment-history-action">
                <button type="button" class="secondary-button" data-distribution-history-open <?= $distributionHistory === [] ? 'disabled' : '' ?>>Histórico de distribuição</button>
                <span><?= h(count($distributionHistory)) ?> distribuição(ões) registrada(s)</span>
            </div>

            <?php if ($managedQueueStatus !== []): ?>
                <div class="recomecar-managed-queue">
                    <div class="recomecar-managed-queue-head">
                        <strong>Distribuições em andamento</strong>
                        <span>Status por ação e analista</span>
                    </div>
                    <div class="recomecar-managed-queue-list">
                        <?php foreach ($managedQueueStatus as $queue): ?>
                            <?php
                                $queueTotal = (int) ($queue['total'] ?? 0);
                                $queueAnalyzed = (int) ($queue['analisadas'] ?? 0);
                                $queuePending = (int) ($queue['pendentes'] ?? 0);
                                $queueOpen = (int) ($queue['abertas'] ?? 0);
                                $queueDone = $queueTotal > 0 && $queueOpen === 0;
                            ?>
                            <article class="recomecar-managed-queue-item <?= $queueDone ? 'is-done' : 'is-open' ?>">
                                <header>
                                    <strong><?= h($queue['usuario_nome'] ?? 'Analista') ?></strong>
                                    <span><?= $queueDone ? 'Concluído' : 'Não concluído' ?></span>
                                </header>
                                <p><?= h($valueOrDash(($queue['municipio_nome'] ?? '') . '/' . ($queue['uf'] ?? '') . ' - ' . ($queue['localidade'] ?? ''))) ?></p>
                                <div>
                                    <small>Total: <?= h($queueTotal) ?></small>
                                    <small>Analisadas: <?= h($queueAnalyzed) ?></small>
                                    <small>Pendentes: <?= h($queuePending) ?></small>
                                    <small><?= h($strategyLabel($queue['estrategia'] ?? '')) ?></small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php $assignmentTotalRecords = (int) ($assignmentSummary['total_recorte'] ?? 0); ?>
            <form method="post" action="<?= h(url('/gestor/recomecar/analise/distribuir')) ?>" class="recomecar-assignment-form js-prevent-double-submit" data-recomecar-assignment-form data-assignment-preview-url="<?= h(url('/gestor/recomecar/analise/distribuicao-preview')) ?>" data-assignment-total-records="<?= h($assignmentTotalRecords) ?>">
                <?= csrf_field() ?>
                <?= idempotency_field('gestor.recomecar.analysis.assign') ?>
                <?php $renderFilterFields($filters, $page, ['acao_id', 'acao_busca', 'data_inicio', 'data_fim']); ?>
                <div class="dti-cosigner-picker recomecar-assignment-picker" data-dti-cosigner-picker data-dti-cosigner-name="analistas_usuarios[]" data-dti-cosigner-empty="Nenhum analista selecionado." data-dti-cosigner-search-text="Digite para buscar usuários ativos.">
                    <label class="field smart-search-field">
                        <span>Buscar usuário analista</span>
                        <input type="search" placeholder="Digite nome, CPF, perfil, órgão ou e-mail" autocomplete="off" data-dti-cosigner-search>
                    </label>
                    <div class="dti-cosigner-selected" data-dti-cosigner-selected aria-live="polite"></div>
                    <div class="dti-cosigner-hint" data-dti-cosigner-status>Digite para buscar usuários ativos.</div>
                    <div class="dti-cosigner-options" data-dti-cosigner-options>
                        <?php foreach ($analysisUsers as $analysisUser): ?>
                            <?php
                                $analystLabel = trim((string) ($analysisUser['nome'] ?? ''));
                                $analystMeta = trim((string) ($analysisUser['cpf'] ?? '') . ' - ' . (string) ($analysisUser['perfil'] ?? '') . (($analysisUser['orgao'] ?? '') !== '' ? ' - ' . (string) $analysisUser['orgao'] : ''));
                                $analystSearch = trim($analystLabel . ' ' . $analystMeta . ' ' . ($analysisUser['email'] ?? '') . ' ' . ($analysisUser['unidade_setor'] ?? ''));
                            ?>
                            <button type="button" data-dti-cosigner-option data-id="<?= h((int) ($analysisUser['id'] ?? 0)) ?>" data-label="<?= h($analystLabel) ?>" data-meta="<?= h($analystMeta) ?>" data-search="<?= h($analystSearch) ?>">
                                <strong><?= h($analystLabel) ?></strong>
                                <span><?= h($analystMeta) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="recomecar-assignment-config" data-assignment-config hidden>
                    <div class="recomecar-assignment-scope">
                        <label class="field styled-field smart-search-field">
                            <span>Ação da distribuição</span>
                            <input type="search" name="filters[acao_busca]" value="<?= h(($filters['acao_busca'] ?? '') !== '' ? $filters['acao_busca'] : $selectedActionLabel) ?>" list="recomecar-analise-distribuicao-acoes-list" placeholder="Digite para buscar a ação" data-smart-search data-smart-target="recomecar_distribuicao_acao_id" data-assignment-action-search autocomplete="off" required>
                            <input type="hidden" name="filters[acao_id]" value="<?= h($filters['acao_id'] ?? '') ?>" data-smart-hidden="recomecar_distribuicao_acao_id" data-assignment-action-id>
                            <datalist id="recomecar-analise-distribuicao-acoes-list">
                                <?php foreach ($acoes ?? [] as $acao): ?>
                                    <option value="<?= h($actionOptionLabel($acao)) ?>" data-id="<?= h($acao['id']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </label>
                        <div class="recomecar-assignment-rule-note">
                            A distribuição será aplicada somente aos registros da ação selecionada.
                        </div>
                    </div>

                    <div class="recomecar-assignment-period-fields" data-assignment-period-fields>
                        <label class="field styled-field">
                            <span>Período inicial</span>
                            <input type="date" name="periodo_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>" required>
                        </label>
                        <label class="field styled-field">
                            <span>Período final</span>
                            <input type="date" name="periodo_fim" value="<?= h($filters['data_fim'] ?? '') ?>" required>
                        </label>
                    </div>

                    <div class="recomecar-assignment-strategy">
                        <label class="field styled-field">
                            <span>Forma de distribuição</span>
                            <select name="distribuicao_estrategia" data-assignment-strategy required>
                                <option value="">Selecione a forma</option>
                                <option value="pares_impares">Registros pares e ímpares</option>
                                <option value="blocos">Blocos sequenciais</option>
                            </select>
                        </label>
                        <div class="recomecar-assignment-rule-note" data-assignment-rule-note>
                            A distribuição calcula automaticamente os registros encontrados na ação e no período selecionados.
                        </div>
                    </div>
                </div>

                <div class="recomecar-assignment-preview" data-assignment-preview hidden>
                    <div class="recomecar-assignment-preview-head">
                        <strong>Prévia da distribuição</strong>
                        <span data-assignment-preview-total>Nenhum registro calculado.</span>
                    </div>
                    <div class="recomecar-assignment-preview-list" data-assignment-preview-list></div>
                </div>

                <div class="recomecar-assignment-actions">
                    <small data-assignment-rule-status>Selecione os analistas para abrir as regras de distribuição.</small>
                    <button type="submit" class="primary-button" data-loading-text="Distribuindo..." disabled>Distribuir registros</button>
                </div>
            </form>
        </section>
        <dialog class="recomecar-analysis-modal recomecar-history-modal recomecar-distribution-history-modal" data-distribution-history-modal>
            <div class="recomecar-analysis-modal-header">
                <span class="recomecar-analysis-modal-icon" aria-hidden="true">H</span>
                <div>
                    <span class="eyebrow">Histórico de distribuição</span>
                    <h3>Distribuições feitas por você</h3>
                    <p>Acompanhe a ação emergencial, estratégia e status dos analistas em cada distribuição.</p>
                </div>
                <button type="button" class="recomecar-analysis-modal-close" data-modal-close aria-label="Fechar">Fechar</button>
            </div>
            <div class="recomecar-analysis-modal-body">
                <div class="recomecar-distribution-history-filters">
                    <label class="field styled-field">
                        <span>Buscar</span>
                        <input type="search" placeholder="Ação, município, analista ou perfil" data-distribution-history-search>
                    </label>
                    <label class="field styled-field">
                        <span>Status</span>
                        <select data-distribution-history-status>
                            <option value="">Todos</option>
                            <option value="em_andamento">Em andamento</option>
                            <option value="concluida">Concluídas</option>
                        </select>
                    </label>
                    <label class="field styled-field">
                        <span>Estratégia</span>
                        <select data-distribution-history-strategy>
                            <option value="">Todas</option>
                            <option value="pares_impares">Pares e ímpares</option>
                            <option value="blocos">Blocos sequenciais</option>
                        </select>
                    </label>
                </div>

                <div class="recomecar-distribution-history-list" data-distribution-history-list>
                    <?php if ($distributionHistory === []): ?>
                        <div class="recomecar-analysis-modal-empty">Ainda não há distribuições feitas por você.</div>
                    <?php else: ?>
                        <div class="recomecar-analysis-modal-empty" data-distribution-history-prompt>Aplique um filtro para visualizar o histórico de distribuições.</div>
                        <?php foreach ($distributionHistory as $distribution): ?>
                            <?php
                                $distributionStatus = (string) ($distribution['status'] ?? 'em_andamento');
                                $distributionSearch = trim(
                                    (string) ($distribution['acao_label'] ?? '') . ' '
                                    . (string) ($distribution['acao_id'] ?? '') . ' '
                                    . (string) ($distribution['estrategia'] ?? '') . ' '
                                    . implode(' ', array_map(
                                        static fn (array $analyst): string => (string) ($analyst['nome'] ?? '') . ' ' . (string) ($analyst['perfil'] ?? ''),
                                        is_array($distribution['analistas'] ?? null) ? $distribution['analistas'] : []
                                    ))
                                );
                            ?>
                            <article class="recomecar-distribution-history-item" data-distribution-history-item data-status="<?= h($distributionStatus) ?>" data-strategy="<?= h($distribution['estrategia'] ?? '') ?>" data-search="<?= h($distributionSearch) ?>" hidden>
                                <header>
                                    <div>
                                        <strong>Ação #<?= h((int) ($distribution['acao_id'] ?? 0)) ?> - <?= h($valueOrDash($distribution['acao_label'] ?? '')) ?></strong>
                                        <span><?= h($strategyLabel($distribution['estrategia'] ?? '')) ?> | <?= h($dateOnlyLabel($distribution['periodo_inicio'] ?? '')) ?> até <?= h($dateOnlyLabel($distribution['periodo_fim'] ?? '')) ?></span>
                                    </div>
                                    <em class="<?= $distributionStatus === 'concluida' ? 'is-done' : 'is-open' ?>"><?= $distributionStatus === 'concluida' ? 'Concluída' : 'Em andamento' ?></em>
                                </header>
                                <div class="recomecar-distribution-history-metrics">
                                    <span>Total: <?= h((int) ($distribution['total'] ?? 0)) ?></span>
                                    <span>Analisadas: <?= h((int) ($distribution['analisadas'] ?? 0)) ?></span>
                                    <span>Pendentes: <?= h((int) ($distribution['pendentes'] ?? 0)) ?></span>
                                    <span>Distribuída em: <?= h($dateLabel($distribution['distribuido_em'] ?? '')) ?></span>
                                </div>
                                <div class="recomecar-distribution-history-analysts">
                                    <?php foreach (($distribution['analistas'] ?? []) as $analyst): ?>
                                        <?php $analystOpen = (int) ($analyst['abertas'] ?? 0) > 0; ?>
                                        <div>
                                            <strong><?= h($valueOrDash($analyst['nome'] ?? 'Analista')) ?></strong>
                                            <span><?= h($valueOrDash($analyst['perfil'] ?? '')) ?></span>
                                            <small><?= $analystOpen ? 'Não concluído' : 'Concluído' ?> | Total: <?= h((int) ($analyst['total'] ?? 0)) ?> | Pendentes: <?= h((int) ($analyst['pendentes'] ?? 0)) ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <div class="recomecar-analysis-modal-empty" data-distribution-history-empty hidden>Nenhuma distribuição encontrada para os filtros informados.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="recomecar-analysis-modal-footer">
                <button type="button" class="secondary-button" data-modal-close>Fechar</button>
                <span><?= h(count($distributionHistory)) ?> registro(s) de distribuição.</span>
            </div>
        </dialog>
    <?php elseif (($currentUser['perfil'] ?? '') === 'gestor'): ?>
        <section class="recomecar-assignment-notice no-print">
            <strong>Fila de analise individual</strong>
            <span>Quando houver distribuição ativa, esta tela mostra apenas os registros atribuídos a <?= h($currentUser['nome'] ?? 'você') ?>.</span>
        </section>
    <?php endif; ?>

    <section class="records-filter-panel delivery-filter-panel accountability-filter-panel recomecar-filter-panel no-print">
        <div class="table-heading">
            <h2>Filtros inteligentes</h2>
            <span>Use ação, localidade, período, busca textual, aptidão e status de análise.</span>
        </div>
        <form method="get" action="<?= h(url('/gestor/recomecar/analise')) ?>" class="accountability-filter-form recomecar-filter-form">
            <div class="accountability-filter-main recomecar-filter-main">
                <div class="recomecar-filter-group recomecar-filter-group-search">
                    <label class="field styled-field recomecar-filter-field recomecar-filter-field-query">
                        <span>Buscar</span>
                        <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" placeholder="Nome, CPF ou protocolo">
                    </label>

                    <label class="field styled-field smart-search-field recomecar-filter-field recomecar-filter-field-action">
                        <span>Ação</span>
                        <input type="search" name="acao_busca" value="<?= h(($filters['acao_busca'] ?? '') !== '' ? $filters['acao_busca'] : $selectedActionLabel) ?>" list="recomecar-analise-acoes-list" placeholder="Digite para buscar a ação" data-smart-search data-smart-target="recomecar_analise_acao_id" autocomplete="off">
                        <input type="hidden" name="acao_id" value="<?= h($filters['acao_id'] ?? '') ?>" data-smart-hidden="recomecar_analise_acao_id">
                        <datalist id="recomecar-analise-acoes-list">
                            <?php foreach ($acoes ?? [] as $acao): ?>
                                <option value="<?= h($actionOptionLabel($acao)) ?>" data-id="<?= h($acao['id']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </label>

                    <label class="field styled-field recomecar-filter-field recomecar-filter-field-location">
                        <span>Localidade, bairro ou comunidade</span>
                        <input type="search" name="localidade_busca" value="<?= h($filters['localidade_busca'] ?? '') ?>" placeholder="Digite localidade, bairro ou comunidade">
                    </label>
                </div>

                <div class="recomecar-filter-group recomecar-filter-group-status">
                    <label class="field styled-field recomecar-filter-field recomecar-filter-field-status">
                        <span>Aptidão</span>
                        <select name="aptidao">
                            <option value="todas" <?= ($filters['aptidao'] ?? 'todas') === 'todas' ? 'selected' : '' ?>>Aptas e inaptas</option>
                            <option value="apta" <?= ($filters['aptidao'] ?? '') === 'apta' ? 'selected' : '' ?>>Somente aptas</option>
                            <option value="inapta" <?= ($filters['aptidao'] ?? '') === 'inapta' ? 'selected' : '' ?>>Somente inaptas</option>
                        </select>
                    </label>

                    <label class="field styled-field recomecar-filter-field recomecar-filter-field-status">
                        <span>Análise</span>
                        <select name="analise">
                            <option value="pendente" <?= ($filters['analise'] ?? 'pendente') === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                            <option value="analisado" <?= ($filters['analise'] ?? '') === 'analisado' ? 'selected' : '' ?>>Analisadas</option>
                            <option value="todas" <?= ($filters['analise'] ?? '') === 'todas' ? 'selected' : '' ?>>Todas</option>
                        </select>
                    </label>

                    <label class="field styled-field recomecar-filter-field recomecar-filter-field-status">
                        <span>Etapa Recomeçar</span>
                        <select name="status_entrega">
                            <option value="" <?= ($filters['status_entrega'] ?? '') === '' ? 'selected' : '' ?>>Todos</option>
                            <option value="registrado" <?= ($filters['status_entrega'] ?? '') === 'registrado' ? 'selected' : '' ?>>Registrado</option>
                            <option value="entregue" <?= ($filters['status_entrega'] ?? '') === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                        </select>
                    </label>

                    <?php if (($currentUser['perfil'] ?? '') === 'administrador'): ?>
                        <label class="field styled-field smart-search-field recomecar-filter-field recomecar-filter-field-status">
                            <span>Analista</span>
                            <input type="search" name="analista_busca" value="<?= h(($filters['analista_busca'] ?? '') !== '' ? $filters['analista_busca'] : $selectedAnalystLabel) ?>" list="recomecar-analise-analistas-list" placeholder="Digite nome, CPF, perfil, órgão ou e-mail" data-smart-search data-smart-target="recomecar_analise_analista_id" autocomplete="off">
                            <input type="hidden" name="analista_id" value="<?= h($filters['analista_id'] ?? '') ?>" data-smart-hidden="recomecar_analise_analista_id">
                            <datalist id="recomecar-analise-analistas-list">
                                <?php foreach ($analysisUsers as $analysisUser): ?>
                                    <?php
                                        $analystOptionLabel = trim(
                                            (string) ($analysisUser['nome'] ?? '')
                                            . (($analysisUser['perfil'] ?? '') !== '' ? ' - ' . (string) $analysisUser['perfil'] : '')
                                        );
                                        $analystOptionSearch = trim(
                                            $analystOptionLabel . ' '
                                            . 'Usuário #' . (string) ($analysisUser['id'] ?? '') . ' '
                                            . (string) ($analysisUser['cpf'] ?? '') . ' '
                                            . (string) ($analysisUser['perfil'] ?? '') . ' '
                                            . (string) ($analysisUser['orgao'] ?? '') . ' '
                                            . (string) ($analysisUser['unidade_setor'] ?? '') . ' '
                                            . (string) ($analysisUser['email'] ?? '')
                                        );
                                    ?>
                                    <option value="<?= h($analystOptionLabel) ?>" data-id="<?= h((int) ($analysisUser['id'] ?? 0)) ?>" data-search="<?= h($analystOptionSearch) ?>" label="<?= h($analystOptionLabel) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
            <div class="accountability-filter-side recomecar-filter-side">
                <div class="accountability-date-range">
                    <label class="field styled-field recomecar-filter-field recomecar-filter-field-date">
                        <span>Início do cadastro</span>
                        <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
                    </label>
                    <label class="field styled-field recomecar-filter-field recomecar-filter-field-date">
                        <span>Fim do cadastro</span>
                        <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
                    </label>
                </div>
                <div class="records-filter-actions delivery-history-filter-actions accountability-filter-actions">
                    <button type="submit" class="primary-button">Filtrar</button>
                    <a class="secondary-button" href="<?= h(url('/gestor/recomecar/analise')) ?>">Limpar</a>
                </div>
            </div>
        </form>
    </section>

    <?php if (!$hasAppliedFilters): ?>
        <section class="action-empty-panel records-empty-panel no-print">
            <h2>Aplique um filtro para gerar a lista de análise</h2>
            <p>Por segurança operacional, a tela de análise não carrega registros sem recorte.</p>
        </section>
    <?php else: ?>
        <?php foreach ($aptitudeSections as $aptitude => $sectionTitle): ?>
            <section class="recomecar-analysis-section">
                <div class="table-heading">
                    <h2><?= h($sectionTitle) ?></h2>
                    <span><?= h(count($recordsByAptitude[$aptitude] ?? [])) ?> registro(s) nesta página</span>
                </div>

                <div class="recomecar-analysis-table-wrap">
                    <table class="records-table recomecar-analysis-table">
                        <thead>
                            <tr>
                                <th>Registro</th>
                                <th>Residência</th>
                                <th>Família</th>
                                <th>Regra</th>
                                <th>Análise</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recordsByAptitude[$aptitude] ?? [] as $record): ?>
                                <?php
                                $familiaId = (int) ($record['familia_id'] ?? 0);
                                $residenciaId = (int) ($record['residencia_id'] ?? 0);
                                $formId = 'recomecar-analysis-form-' . $familiaId;
                                $docs = array_merge(
                                    $documentsByRecord['residencia:' . $residenciaId] ?? [],
                                    $documentsByRecord['familia:' . $familiaId] ?? []
                                );
                                $history = $historyByRecord[$familiaId] ?? [];
                                $isAnalyzed = trim((string) ($record['ultima_analise_em'] ?? '')) !== '';
                                $isAssignmentClosed = trim((string) ($record['atribuicao_concluida_em'] ?? '')) !== '';
                                $canEditRecord = ($currentUser['perfil'] ?? '') === 'administrador' || !$isAssignmentClosed;
                                $isEditOpen = $openEditId === $familiaId;
                                $displaySequence = $displaySequenceByFamilyId[$familiaId] ?? $familiaId;
                                $editRecord = ($isEditOpen && $analysisValidationFamiliaId === $familiaId)
                                    ? array_replace($record, $analysisOldInput)
                                    : $record;
                                $benefitName = trim((string) ($editRecord['beneficio_social_nome'] ?? ''));
                                $hasSocialBenefit = !empty($editRecord['recebe_beneficio_social']) || $benefitName !== '';
                                $representativeFields = [
                                    $editRecord['representante_nome'] ?? '',
                                    $editRecord['representante_cpf'] ?? '',
                                    $editRecord['representante_rg'] ?? '',
                                    $editRecord['representante_orgao_expedidor'] ?? '',
                                    $editRecord['representante_data_nascimento'] ?? '',
                                    $editRecord['representante_telefone'] ?? '',
                                    $editRecord['representante_email'] ?? '',
                                ];
                                $hasRepresentative = array_filter($representativeFields, static fn (mixed $value): bool => trim((string) $value) !== '') !== [];
                                $pendingFields = $pendingFieldsForRecord($editRecord);
                                ?>
                                <tr id="familia-<?= h($familiaId) ?>" class="recomecar-analysis-card-row <?= $isAnalyzed ? 'is-analysis-done' : '' ?> <?= $aptitude === 'apta' ? 'is-apta' : 'is-inapta' ?>">
                                    <td colspan="6">
                                        <article class="recomecar-family-card">
                                            <div class="recomecar-family-card-main">
                                                <div class="recomecar-family-card-title">
                                                    <span class="recomecar-family-card-id"><span class="recomecar-family-card-id-text">#<?= h($displaySequence) ?></span></span>
                                                    <div>
                                                        <strong><?= h($field($record, 'responsavel_nome')) ?></strong>
                                                        <span>Protocolo <?= h($field($record, 'protocolo')) ?> | <?= h($field($record, 'municipio_nome')) ?>/<?= h($field($record, 'uf')) ?></span>
                                                    </div>
                                                </div>
                                                <div class="recomecar-family-card-status">
                                                    <span class="recomecar-delivery-status <?= $aptitude === 'apta' ? 'is-delivered' : 'is-pending' ?>"><?= $aptitude === 'apta' ? 'Apta' : 'Inapta' ?></span>
                                                    <span class="recomecar-delivery-status <?= $isAnalyzed ? 'is-delivered' : 'is-pending' ?>"><?= $isAnalyzed ? 'Analisado' : 'Pendente' ?></span>
                                                    <?php if ($isAssignmentClosed): ?>
                                                        <span class="recomecar-delivery-status is-delivered">Fila concluída</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="recomecar-family-card-grid">
                                                <div class="recomecar-family-card-info">
                                                    <span>Residência</span>
                                                    <strong><?= h($field($record, 'bairro_comunidade')) ?></strong>
                                                    <small><?= h($field($record, 'endereco')) ?></small>
                                                </div>
                                                <div class="recomecar-family-card-info">
                                                    <span>Família</span>
                                                    <strong><?= h($field($record, 'beneficiario_nome')) ?></strong>
                                                    <small>CPF: <?= h($field($record, 'responsavel_cpf')) ?> | Integrantes: <?= h($field($record, 'quantidade_integrantes')) ?></small>
                                                </div>
                                                <div class="recomecar-family-card-info">
                                                    <span>Ação emergencial</span>
                                                    <strong><?= h($field($record, 'localidade')) ?></strong>
                                                    <small><?= h($field($record, 'tipo_evento')) ?> | <?= h($field($record, 'condicao_residencia')) ?></small>
                                                </div>
                                                <div class="recomecar-family-card-info">
                                                    <span>Regra do Recomeçar</span>
                                                    <strong><?= h($valueOrDash($record['motivo_inaptidao'] ?? 'Sem bloqueio')) ?></strong>
                                                    <small><?= $isAnalyzed ? 'Analisado em ' . h($dateLabel($record['ultima_analise_em'])) : 'Aguardando conferência' ?> | Analista: <?= h($valueOrDash($record['analista_nome'] ?? 'Não atribuído')) ?></small>
                                                </div>
                                            </div>

                                            <?php if ($pendingFields !== []): ?>
                                                <div class="recomecar-family-pending-panel">
                                                    <strong>Campos pendentes</strong>
                                                    <div>
                                                        <?php foreach ($pendingFields as $pendingLabel): ?>
                                                            <span><?= h($pendingLabel) ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="recomecar-family-pending-panel is-clear">
                                                    <strong>Campos pendentes</strong>
                                                    <div><span>Sem pendências principais</span></div>
                                                </div>
                                            <?php endif; ?>

                                            <div class="recomecar-analysis-actions">
                                                <?php if ($canEditRecord): ?>
                                                    <button type="button" class="secondary-button <?= $isEditOpen ? 'is-active' : '' ?>" data-analysis-edit-toggle="<?= h($familiaId) ?>" aria-expanded="<?= $isEditOpen ? 'true' : 'false' ?>" aria-controls="<?= h($formId) ?>-panel"><?= $isEditOpen ? 'Recolher edição' : 'Editar campos' ?></button>
                                                <?php else: ?>
                                                    <button type="button" class="secondary-button" disabled>Fila concluída</button>
                                                <?php endif; ?>
                                                <button type="button" class="secondary-button" data-recomecar-docs-open="<?= h($familiaId) ?>" aria-controls="recomecar-docs-drawer" aria-expanded="false">Documentos (<?= h(count($docs)) ?>)</button>
                                                <button type="button" class="secondary-button" data-analysis-history-open="<?= h($familiaId) ?>">Histórico (<?= h(count($history)) ?>)</button>
                                            </div>
                                        </article>
                                    </td>
                                </tr>
                                <tr class="recomecar-analysis-edit-row">
                                    <td colspan="6">
                                        <div id="<?= h($formId) ?>-panel" class="recomecar-analysis-edit-panel" data-analysis-edit-panel="<?= h($familiaId) ?>" <?= ($isEditOpen && $canEditRecord) ? '' : 'hidden' ?>>
                                            <form id="<?= h($formId) ?>" method="post" action="<?= h(url('/gestor/recomecar/analise/' . $familiaId)) ?>" class="recomecar-analysis-form js-prevent-double-submit">
                                                <?= csrf_field() ?>
                                                <?= idempotency_field('gestor.recomecar.analysis.update.' . $familiaId) ?>
                                                <?php $renderFilterFields($filters, $page); ?>
                                                <div class="recomecar-analysis-locked">
                                                    <span>Bloqueados: protocolo <?= h($field($record, 'protocolo')) ?></span>
                                                    <span>Ação #<?= h($field($record, 'acao_id')) ?> - <?= h($field($record, 'tipo_evento')) ?></span>
                                                </div>

                                                <div class="recomecar-analysis-edit-sections">
                                                    <section class="recomecar-analysis-edit-section">
                                                        <header>
                                                            <span>01</span>
                                                            <div>
                                                                <h3>Residência</h3>
                                                                <p>Localização, imóvel e capacidade vinculada ao cadastro.</p>
                                                            </div>
                                                        </header>
                                                        <div class="recomecar-analysis-grid">
                                                    <label class="field<?= $pendingClass($pendingFields, 'bairro_comunidade') ?>"><span>Bairro/comunidade</span><input name="bairro_comunidade" value="<?= h($editRecord['bairro_comunidade'] ?? '') ?>" required></label>
                                                    <label class="field<?= $pendingClass($pendingFields, 'endereco') ?>"><span>Endereço</span><input name="endereco" value="<?= h($editRecord['endereco'] ?? '') ?>" required></label>
                                                    <label class="field"><span>Complemento</span><input name="complemento" value="<?= h($editRecord['complemento'] ?? '') ?>"></label>
                                                    <label class="field<?= $pendingClass($pendingFields, 'imovel') ?>"><span>Imóvel</span><select name="imovel">
                                                    <option value="">Selecionar</option>
                                                    <?php foreach (['proprio' => 'Próprio', 'alugado' => 'Alugado', 'cedido' => 'Cedido'] as $value => $label): ?>
                                                        <option value="<?= h($value) ?>" <?= (string) ($editRecord['imovel'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select></label>
                                                <label class="field<?= $pendingClass($pendingFields, 'condicao_residencia') ?>"><span>Condição da residência</span><select name="condicao_residencia">
                                                    <option value="">Selecionar</option>
                                                    <?php foreach (['perda_total' => 'Perda total', 'perda_parcial' => 'Perda parcial', 'nao_atingida' => 'Não atingida'] as $value => $label): ?>
                                                        <option value="<?= h($value) ?>" <?= (string) ($editRecord['condicao_residencia'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select></label>
                                                <label class="field<?= $pendingClass($pendingFields, 'quantidade_familias') ?>"><span>Qtd. famílias</span><input type="number" name="quantidade_familias" min="1" value="<?= h($editRecord['quantidade_familias'] ?? '1') ?>" required></label>
                                                <label class="field<?= $pendingClass($pendingFields, 'latitude') ?>"><span>Latitude</span><input name="latitude" value="<?= h($editRecord['latitude'] ?? '') ?>" inputmode="decimal" data-analysis-latitude></label>
                                                <label class="field<?= $pendingClass($pendingFields, 'longitude') ?>"><span>Longitude</span><input name="longitude" value="<?= h($editRecord['longitude'] ?? '') ?>" inputmode="decimal" data-analysis-longitude></label>
                                                <div class="recomecar-analysis-map-card<?= $pendingClass($pendingFields, 'coordenadas') ?>">
                                                    <div class="recomecar-analysis-map-head">
                                                        <div>
                                                            <strong>Ponto da residência</strong>
                                                            <span>Arraste o marcador ou clique no mapa para ajustar a localização.</span>
                                                        </div>
                                                        <span data-analysis-map-status><?= trim((string) ($editRecord['latitude'] ?? '')) !== '' && trim((string) ($editRecord['longitude'] ?? '')) !== '' ? 'Ponto carregado' : 'Clique no mapa para definir o ponto' ?></span>
                                                    </div>
                                                    <div class="recomecar-analysis-map" data-analysis-map></div>
                                                </div>
                                                        </div>
                                                    </section>

                                                    <section class="recomecar-analysis-edit-section">
                                                        <header>
                                                            <span>02</span>
                                                            <div>
                                                                <h3>Responsável familiar</h3>
                                                                <p>Identificação, contato e dados sociofamiliares.</p>
                                                            </div>
                                                        </header>
                                                        <div class="recomecar-analysis-grid">
                                                <label class="field<?= $pendingClass($pendingFields, 'responsavel_nome') ?>"><span>Responsável</span><input name="responsavel_nome" value="<?= h($editRecord['responsavel_nome'] ?? '') ?>" required></label>
                                                <label class="field<?= $pendingClass($pendingFields, 'responsavel_cpf') ?>"><span>CPF responsável</span><input name="responsavel_cpf" value="<?= h($editRecord['responsavel_cpf'] ?? '') ?>"></label>
                                                <label class="field"><span>RG</span><input name="responsavel_rg" value="<?= h($editRecord['responsavel_rg'] ?? '') ?>"></label>
                                                <label class="field"><span>Órgão expedidor</span><input name="responsavel_orgao_expedidor" value="<?= h($editRecord['responsavel_orgao_expedidor'] ?? '') ?>"></label>
                                                <label class="field"><span>Sexo</span><select name="responsavel_sexo">
                                                    <option value="">Selecionar</option>
                                                    <?php foreach (['feminino' => 'Feminino', 'masculino' => 'Masculino', 'outro' => 'Outro', 'nao_informado' => 'Não informado'] as $value => $label): ?>
                                                        <option value="<?= h($value) ?>" <?= (string) ($editRecord['responsavel_sexo'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select></label>
                                                <label class="field"><span>Nascimento</span><input type="date" name="data_nascimento" value="<?= h($dateValue($editRecord['data_nascimento'] ?? '')) ?>"></label>
                                                <label class="field"><span>Telefone</span><input name="telefone" value="<?= h($editRecord['telefone'] ?? '') ?>"></label>
                                                <label class="field<?= $pendingClass($pendingFields, 'email') ?>"><span>E-mail</span><input type="email" name="email" value="<?= h($editRecord['email'] ?? '') ?>"></label>
                                                <label class="field<?= $pendingClass($pendingFields, 'quantidade_integrantes') ?>"><span>Qtd. integrantes</span><input type="number" name="quantidade_integrantes" min="1" value="<?= h($editRecord['quantidade_integrantes'] ?? '1') ?>" required></label>
                                                <label class="field<?= $pendingClass($pendingFields, 'renda_familiar') ?>"><span>Renda familiar</span><select name="renda_familiar">
                                                    <option value="">Selecionar</option>
                                                    <option value="0_3_salarios" <?= (string) ($editRecord['renda_familiar'] ?? '') === '0_3_salarios' ? 'selected' : '' ?>>0 a 3 salários</option>
                                                    <option value="acima_3_salarios" <?= (string) ($editRecord['renda_familiar'] ?? '') === 'acima_3_salarios' ? 'selected' : '' ?>>Acima de 3 salários</option>
                                                </select></label>
                                                <label class="field<?= $pendingClass($pendingFields, 'situacao_familia') ?>"><span>Situação</span><select name="situacao_familia">
                                                    <option value="">Selecionar</option>
                                                    <?php foreach (['desabrigado' => 'Desabrigado', 'desalojado' => 'Desalojado', 'aluguel_social' => 'Aluguel social', 'permanece_residencia' => 'Permanece na residência'] as $value => $label): ?>
                                                        <option value="<?= h($value) ?>" <?= (string) ($editRecord['situacao_familia'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select></label>
                                                        </div>
                                                    </section>

                                                    <section class="recomecar-analysis-edit-section">
                                                        <header>
                                                            <span>03</span>
                                                            <div>
                                                                <h3>Composição e benefícios</h3>
                                                                <p>Marcadores de prioridade e benefício social informado.</p>
                                                            </div>
                                                        </header>
                                                        <div class="recomecar-analysis-benefit-section">
                                                            <div class="recomecar-analysis-grid recomecar-analysis-grid-flags">
                                                <?php foreach (['possui_criancas' => 'Crianças', 'possui_idosos' => 'Idosos', 'possui_pcd' => 'PCD', 'possui_gestantes' => 'Gestantes', 'cadastro_concluido' => 'Cadastro concluído'] as $name => $label): ?>
                                                    <label class="checkbox-card"><input type="checkbox" name="<?= h($name) ?>" value="1" <?= !empty($editRecord[$name]) ? 'checked' : '' ?>> <span><?= h($label) ?></span></label>
                                                <?php endforeach; ?>
                                                <label class="checkbox-card recomecar-benefit-toggle-card"><input type="checkbox" name="recebe_beneficio_social" value="1" <?= $hasSocialBenefit ? 'checked' : '' ?> data-analysis-benefit-toggle> <span>Benefício social</span></label>
                                                            </div>
                                                            <div class="recomecar-benefit-panel" data-analysis-benefit-panel <?= $hasSocialBenefit ? '' : 'hidden' ?>>
                                                <label class="field<?= $pendingClass($pendingFields, 'beneficio_social_nome') ?>"><span>Nome do benefício</span><input name="beneficio_social_nome" value="<?= h($editRecord['beneficio_social_nome'] ?? '') ?>" <?= $hasSocialBenefit ? '' : 'disabled' ?> data-analysis-benefit-name></label>
                                                            </div>
                                                        </div>
                                                    </section>

                                                    <section class="recomecar-analysis-edit-section">
                                                        <header>
                                                            <span>04</span>
                                                            <div>
                                                                <h3>Perdas e conclusão</h3>
                                                                <p>Campos de observação usados na revisão operacional.</p>
                                                            </div>
                                                        </header>
                                                        <div class="recomecar-analysis-textarea-section">
                                                <label class="field recomecar-analysis-textarea-field"><span>Perdas de bens móveis</span><textarea name="perdas_bens_moveis" rows="4"><?= h($editRecord['perdas_bens_moveis'] ?? '') ?></textarea></label>
                                                <label class="field recomecar-analysis-textarea-field"><span>Observações de conclusão</span><textarea name="conclusao_observacoes" rows="4"><?= h($editRecord['conclusao_observacoes'] ?? '') ?></textarea></label>
                                                        </div>
                                                    </section>

                                                    <section class="recomecar-analysis-edit-section recomecar-representative-section">
                                                        <header>
                                                            <span>05</span>
                                                            <div>
                                                                <h3>Representante</h3>
                                                                <p><?= $hasRepresentative ? 'Dados opcionais do representante da família.' : 'Sem representante informado neste registro.' ?></p>
                                                            </div>
                                                            <button type="button" class="secondary-button recomecar-section-toggle" data-analysis-representative-toggle aria-expanded="<?= $hasRepresentative ? 'true' : 'false' ?>"><?= $hasRepresentative ? 'Recolher representante' : 'Adicionar representante' ?></button>
                                                        </header>
                                                        <div class="recomecar-analysis-grid" data-analysis-representative-panel <?= $hasRepresentative ? '' : 'hidden' ?>>
                                                <label class="field"><span>Representante</span><input name="representante_nome" value="<?= h($editRecord['representante_nome'] ?? '') ?>"></label>
                                                <label class="field"><span>CPF representante</span><input name="representante_cpf" value="<?= h($editRecord['representante_cpf'] ?? '') ?>"></label>
                                                <label class="field"><span>RG representante</span><input name="representante_rg" value="<?= h($editRecord['representante_rg'] ?? '') ?>"></label>
                                                <label class="field"><span>Órgão expedidor</span><input name="representante_orgao_expedidor" value="<?= h($editRecord['representante_orgao_expedidor'] ?? '') ?>"></label>
                                                <label class="field"><span>Nascimento</span><input type="date" name="representante_data_nascimento" value="<?= h($dateValue($editRecord['representante_data_nascimento'] ?? '')) ?>"></label>
                                                <label class="field"><span>Sexo</span><select name="representante_sexo">
                                                    <option value="">Não informado</option>
                                                    <?php foreach (['feminino' => 'Feminino', 'masculino' => 'Masculino', 'outro' => 'Outro', 'nao_informado' => 'Não informado'] as $value => $label): ?>
                                                        <option value="<?= h($value) ?>" <?= (string) ($editRecord['representante_sexo'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select></label>
                                                <label class="field"><span>Telefone</span><input name="representante_telefone" value="<?= h($editRecord['representante_telefone'] ?? '') ?>"></label>
                                                <label class="field"><span>E-mail</span><input type="email" name="representante_email" value="<?= h($editRecord['representante_email'] ?? '') ?>"></label>
                                                        </div>
                                                    </section>
                                                </div>
                                                <div class="recomecar-analysis-form-actions">
                                                    <button type="submit" class="primary-button" data-loading-text="Salvando...">Salvar alterações</button>
                                                </div>
                                            </form>

                                            <form method="post" action="<?= h(url('/gestor/recomecar/analise/' . $familiaId . '/analisado')) ?>" class="recomecar-analysis-mark-form js-prevent-double-submit">
                                                <?= csrf_field() ?>
                                                <?= idempotency_field('gestor.recomecar.analysis.mark.' . $familiaId) ?>
                                                <?php $renderFilterFields($filters, $page); ?>
                                                <div class="recomecar-analysis-mark-card">
                                                    <div class="recomecar-analysis-mark-head">
                                                        <strong>Observação da análise</strong>
                                                        <small>Registre uma observação objetiva antes de liberar a família para entrega.</small>
                                                    </div>
                                                    <label class="field">
                                                        <span>Registro para o histórico</span>
                                                        <textarea name="observacao_analise" rows="4" placeholder="Ex.: Dados conferidos nos documentos anexados. Família liberada para entrega."></textarea>
                                                    </label>
                                                    <div class="recomecar-analysis-mark-actions">
                                                        <small>Ao confirmar, o registro fica como analisado e liberado para entrega.</small>
                                                        <button type="submit" class="primary-button" data-loading-text="Registrando...">Marcar como analisado</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>

                                        <template data-recomecar-docs-template="<?= h($familiaId) ?>">
                                            <?php if ($docs === []): ?>
                                                <div class="records-empty-panel">Nenhum documento anexado neste registro.</div>
                                            <?php else: ?>
                                                <div class="recomecar-docs-list">
                                                    <?php foreach ($docs as $document): ?>
                                                        <a href="<?= h($documentUrl($record, $document)) ?>" target="recomecar-doc-frame" data-recomecar-doc-link data-recomecar-doc-mime="<?= h($document['mime_type'] ?? '') ?>">
                                                            <strong><?= h($document['nome_original'] ?? 'Documento') ?></strong>
                                                            <span><?= h($document['tipo_documento'] ?? '') ?> | <?= h($formatBytes($document['tamanho_bytes'] ?? 0)) ?></span>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </template>

                                        <dialog class="recomecar-analysis-modal recomecar-history-modal" data-analysis-history-modal="<?= h($familiaId) ?>">
                                            <div class="recomecar-analysis-modal-header">
                                                <span class="recomecar-analysis-modal-icon" aria-hidden="true">H</span>
                                                <div>
                                                    <span class="eyebrow">Histórico</span>
                                                    <h3>Registro #<?= h($familiaId) ?></h3>
                                                    <p><?= h($field($record, 'responsavel_nome')) ?> - protocolo <?= h($field($record, 'protocolo')) ?></p>
                                                </div>
                                                <button type="button" class="recomecar-analysis-modal-close" data-modal-close aria-label="Fechar">Fechar</button>
                                            </div>
                                            <div class="recomecar-analysis-modal-body">
                                                <?php if ($history === []): ?>
                                                    <div class="recomecar-analysis-modal-empty">Ainda não há histórico de análise para este registro.</div>
                                                <?php else: ?>
                                                    <div class="recomecar-history-list">
                                                        <?php foreach ($history as $log): ?>
                                                            <?php $payload = $decodeHistory($log); ?>
                                                            <article>
                                                                <header>
                                                                    <strong><?= h($log['acao'] === 'analisou_recomecar_familia' ? 'Registro analisado' : 'Dados alterados') ?></strong>
                                                                    <span><?= h($dateLabel($log['criado_em'] ?? '')) ?> por <?= h($valueOrDash($log['usuario_nome'] ?? '')) ?></span>
                                                                </header>
                                                                <?php if (($payload['alteracoes'] ?? []) !== []): ?>
                                                                    <ul>
                                                                        <?php foreach ($payload['alteracoes'] as $change): ?>
                                                                            <li>
                                                                                <strong><?= h($change['rotulo'] ?? $change['campo'] ?? '') ?></strong>
                                                                                <span><?= h($valueOrDash($change['antes'] ?? '')) ?> -> <?= h($valueOrDash($change['depois'] ?? '')) ?></span>
                                                                            </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                <?php elseif (($payload['observacao'] ?? '') !== ''): ?>
                                                                    <p><?= h($payload['observacao']) ?></p>
                                                                <?php else: ?>
                                                                    <p>Registro gravado sem observação adicional.</p>
                                                                <?php endif; ?>
                                                            </article>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="recomecar-analysis-modal-footer">
                                                <span><?= h(count($history)) ?> evento(s) registrado(s)</span>
                                            </div>
                                        </dialog>
                                    </td>
                                </tr>
                                <tr class="recomecar-docs-inline-row" data-recomecar-docs-slot-row="<?= h($familiaId) ?>" hidden>
                                    <td colspan="6">
                                        <div class="recomecar-docs-inline-host" data-recomecar-docs-slot="<?= h($familiaId) ?>"></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (($recordsByAptitude[$aptitude] ?? []) === []): ?>
                                <tr>
                                    <td colspan="6" class="records-empty-cell">Nenhum registro nesta seção.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="records-pagination delivery-pagination no-print" aria-label="Paginação da análise Recomeçar">
                <a class="secondary-button <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= h($pageUrl(max(1, $page - 1))) ?>">Anterior</a>
                <div class="pagination-pages">
                    <?php for ($itemPage = 1; $itemPage <= $totalPages; $itemPage++): ?>
                        <a class="<?= $itemPage === $page ? 'is-active' : '' ?>" href="<?= h($pageUrl($itemPage)) ?>"><?= h($itemPage) ?></a>
                    <?php endfor; ?>
                </div>
                <a class="secondary-button <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h($pageUrl(min($totalPages, $page + 1))) ?>">Próxima</a>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($analysisValidationMessages !== []): ?>
        <dialog class="recomecar-analysis-modal recomecar-validation-modal" data-analysis-validation-modal>
            <div class="recomecar-analysis-modal-header">
                <span class="recomecar-analysis-modal-icon recomecar-analysis-modal-icon-warning" aria-hidden="true">!</span>
                <div>
                    <span class="eyebrow">Salvar alterações</span>
                    <h3>Revise os campos do registro #<?= h($analysisValidationFamiliaId) ?></h3>
                    <p>Foram encontradas pendências antes de gravar as alterações.</p>
                </div>
                <button type="button" class="recomecar-analysis-modal-close" data-modal-close aria-label="Fechar">Fechar</button>
            </div>
            <div class="recomecar-analysis-modal-body recomecar-validation-modal-body">
                <p>Existem informações faltando ou digitadas incorretamente. Corrija os campos abaixo e salve novamente.</p>
                <ul>
                    <?php foreach ($analysisValidationMessages as $message): ?>
                        <li><?= h($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="recomecar-analysis-modal-footer">
                <button type="button" class="primary-button" data-modal-close>Corrigir campos</button>
            </div>
        </dialog>
    <?php endif; ?>

    <aside id="recomecar-docs-drawer" class="recomecar-docs-drawer" data-recomecar-docs-drawer hidden>
        <div class="recomecar-docs-drawer-header">
            <div>
                <span class="eyebrow">Documentos</span>
                <h2 data-recomecar-docs-title>Registro</h2>
            </div>
            <button type="button" class="recomecar-docs-close" data-recomecar-docs-close aria-label="Fechar documentos">Fechar</button>
        </div>
        <div class="recomecar-docs-toolbar">
            <span data-recomecar-docs-count>Nenhum documento</span>
            <a href="#" class="secondary-button recomecar-docs-external" target="_blank" rel="noopener" data-recomecar-docs-external hidden>Abrir em nova aba</a>
        </div>
        <div class="recomecar-docs-drawer-body" data-recomecar-docs-body></div>
        <div class="recomecar-docs-preview">
            <div class="recomecar-docs-placeholder" data-recomecar-docs-placeholder>
                <strong>Selecione um documento</strong>
                <span>Ao abrir um registro, o primeiro anexo disponível aparece aqui para conferência.</span>
            </div>
            <img src="" alt="Documento anexado" data-recomecar-docs-image hidden>
            <iframe name="recomecar-doc-frame" title="Documento anexado" data-recomecar-docs-frame hidden></iframe>
        </div>
    </aside>
</section>
