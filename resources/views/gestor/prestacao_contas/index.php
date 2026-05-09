<?php
$summary = $indicators ?? [];
$filters = $filters ?? [];
$hasAppliedFilters = (bool) ($hasAppliedFilters ?? false);
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'total' => count($details ?? []), 'per_page' => 10];
$pagedDetails = $details ?? [];
$documentDetails = $documentDetails ?? $pagedDetails;
$documentContext = $documentContext ?? [];
$generatedAtText = $generatedAt instanceof DateTimeInterface ? $generatedAt->format('d/m/Y H:i') : date('d/m/Y H:i');
$documentDate = $generatedAt instanceof DateTimeInterface ? $generatedAt->format('d/m/Y') : date('d/m/Y');
$documentCode = 'PC-' . ($generatedAt instanceof DateTimeInterface ? $generatedAt->format('Ymd-His') : date('Ymd-His')) . '-' . strtoupper(substr(hash('sha256', json_encode($filters)), 0, 8));
$signedAtText = $signature !== null && !empty($signature['signed_at']) ? date('d/m/Y H:i', strtotime((string) $signature['signed_at'])) : '';
$signatureHash = (string) ($signature['hash'] ?? '');
$signatureSigners = is_array($signature['assinantes'] ?? null) ? $signature['assinantes'] : [];
$coSignatureStatus = is_array($signature['coassinatura_status'] ?? null) ? $signature['coassinatura_status'] : ($coSignatureStatus ?? ['total' => 0, 'pendentes' => 0, 'autorizados' => 0, 'negados' => 0, 'impressao_liberada' => true, 'solicitacoes' => []]);
$printReady = $signature !== null && (bool) ($signature['impressao_liberada'] ?? $coSignatureStatus['impressao_liberada'] ?? true);
$embedDocument = (bool) ($embedDocument ?? false);
$currentUserId = (int) (current_user()['id'] ?? 0);
$signaturePrincipalId = (int) ($signature['usuario_id'] ?? 0);
$canManageSignature = $signature !== null && $signaturePrincipalId > 0 && $signaturePrincipalId === $currentUserId;
$coSignatureRequests = array_values(array_filter(
    is_array($coSignatureStatus['solicitacoes'] ?? null) ? $coSignatureStatus['solicitacoes'] : [],
    static fn (array $solicitacao): bool =>
        (int) ($solicitacao['coautor_usuario_id'] ?? 0) !== (int) ($solicitacao['solicitante_usuario_id'] ?? 0)
));
if ($signature !== null && $signatureSigners === []) {
    $signatureSigners[] = [
        'tipo' => 'assinante_principal',
        'nome' => $signature['nome'] ?? '',
        'cpf' => $signature['cpf'] ?? '',
        'graduacao' => $signature['graduacao'] ?? '',
        'nome_guerra' => $signature['nome_guerra'] ?? '',
        'matricula_funcional' => $signature['matricula_funcional'] ?? '',
    ];
}
$primarySigner = $signatureSigners[0] ?? [];
$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['pages'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 10));
$firstRecord = (int) (($page - 1) * $perPage) + 1;
$totalRecords = (int) ($pagination['total'] ?? count($details ?? []));
$lastRecord = min($totalRecords, $page * $perPage);
$documentTotalRecords = count($documentDetails);
$documentFirstRecord = $documentTotalRecords > 0 ? 1 : 0;
$documentLastRecord = $documentTotalRecords;
$periodo = 'Todo o período';

if (($filters['data_inicio'] ?? '') !== '' || ($filters['data_fim'] ?? '') !== '') {
    $periodo = ($filters['data_inicio'] ?: 'início') . ' a ' . ($filters['data_fim'] ?: 'hoje');
}

$valueOrDash = static function (mixed $value): string {
    $text = trim((string) $value);

    return $text !== '' ? $text : '-';
};

$softBreak = static function (mixed $value) use ($valueOrDash): string {
    $text = $valueOrDash($value);

    if ($text === '-') {
        return h($text);
    }

    return str_replace(
        ['@', '.', '/', '-'],
        ['<wbr>@', '.<wbr>', '/<wbr>', '-<wbr>'],
        h($text)
    );
};

$formatQuantity = static function (mixed $value): string {
    $number = (float) $value;
    $decimals = abs($number - round($number)) < 0.00001 ? 0 : 2;

    return number_format($number, $decimals, ',', '.');
};

$selectedActionLabel = '';
$actionOptionLabel = static function (array $acao): string {
    return trim(
        (string) ($acao['municipio_nome'] ?? '') . '/' . (string) ($acao['uf'] ?? '')
        . ' - ' . (string) ($acao['localidade'] ?? '')
        . ' - ' . (string) ($acao['tipo_evento'] ?? '')
        . ' - Ação #' . (string) ($acao['id'] ?? '')
    );
};
foreach ($acoes ?? [] as $acao) {
    if ((string) ($filters['acao_id'] ?? '') === (string) $acao['id']) {
        $selectedActionLabel = $actionOptionLabel($acao);
        break;
    }
}

$selectedTypeLabel = '';
foreach ($tipos ?? [] as $tipo) {
    if ((string) ($filters['tipo_ajuda_id'] ?? '') === (string) $tipo['id']) {
        $selectedTypeLabel = $tipo['nome'] . ' (' . $tipo['unidade_medida'] . ')';
        break;
    }
}

$activeFilters = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
unset($activeFilters['acao_id'], $activeFilters['tipo_ajuda_id']);

$filterLabels = [
    'q' => 'Busca',
    'acao_busca' => 'Ação',
    'tipo_ajuda_busca' => 'Tipo de material',
    'localidade_busca' => 'Localidade/bairro',
    'status_operacional' => 'Etapa',
    'data_inicio' => 'Início',
    'data_fim' => 'Fim',
];

$pageUrl = static function (int $targetPage) use ($filters): string {
    $params = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
    $params['pagina'] = max(1, $targetPage);

    return url('/gestor/prestacao-contas') . '?' . http_build_query($params);
};

$printDocumentUrl = static function (bool $autoPrint = true) use ($filters): string {
    $params = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
    $params['assinatura'] = '1';
    $params['embed_document'] = '1';

    if ($autoPrint) {
        $params['print'] = '1';
    }

    return url('/gestor/prestacao-contas') . '?' . http_build_query($params);
};

$materialLabel = $valueOrDash($documentContext['tipos_materiais'] ?? '');
$municipioLabel = $valueOrDash($documentContext['municipios'] ?? '');
$localidadeLabel = $valueOrDash(trim(($documentContext['localidades'] ?? '') . (($documentContext['bairros'] ?? '') !== '' ? ' / ' . $documentContext['bairros'] : '')));
$renderFilterFields = static function (array $filters): void {
    foreach ($filters as $key => $value) {
        if ((string) $value === '') {
            continue;
        }
        echo '<input type="hidden" name="' . h((string) $key) . '" value="' . h((string) $value) . '">' . PHP_EOL;
    }
};
?>

<style media="print">
    @page {
        size: A4;
        margin: 12mm;
    }
</style>

<section class="records-page accountability-page <?= $signature !== null && !$printReady ? 'is-print-blocked' : '' ?>">
    <?php if (!$embedDocument): ?>
    <header class="dashboard-header deliveries-header accountability-header no-print">
        <div>
            <span class="eyebrow">Gestão operacional</span>
            <h1>Prestação de contas</h1>
            <p>Gere o documento nominal de distribuição por ação, material, localidade e período.</p>
        </div>
        <?php if ($hasAppliedFilters && $signature === null): ?>
            <form method="post" action="<?= h(url('/gestor/prestacao-contas/assinar')) ?>" class="inline-form js-prevent-double-submit">
                <?= csrf_field() ?>
                <?= idempotency_field('gestor.prestacao_contas.sign.' . (int) ($documentIdentity['entity_id'] ?? 0)) ?>
                <?php $renderFilterFields($filters); ?>
                <button type="submit" class="primary-button" data-loading-text="Assinando...">Assinar documento</button>
            </form>
            <a class="secondary-button" href="#prestacao-signature-form">Assinatura conjunta</a>
        <?php elseif ($hasAppliedFilters): ?>
            <span class="limit-reached-pill"><?= $printReady ? 'Documento assinado' : 'Aguardando conferência' ?></span>
            <?php if ($canManageSignature): ?>
                <a class="danger-button" href="#prestacao-signature-removal">Gerenciar assinaturas</a>
            <?php endif; ?>
            <?php if ($printReady): ?>
                <a class="primary-link-button" href="<?= h($printDocumentUrl(true)) ?>" target="_blank" rel="noopener" data-prestacao-print-url="<?= h($printDocumentUrl(false)) ?>">Imprimir documento</a>
            <?php else: ?>
                <span class="limit-reached-pill">Impressão bloqueada</span>
            <?php endif; ?>
        <?php endif; ?>
    </header>

    <?php if ($hasAppliedFilters && $signature !== null && $canManageSignature): ?>
        <section class="dti-signature-setup no-print" id="prestacao-signature-removal">
            <div>
                <span class="eyebrow">Gerenciamento de assinaturas</span>
                <h2>Remover assinaturas</h2>
                <p>Somente o assinante principal pode remover a assinatura principal ou remover responsáveis pela conferência selecionados.</p>
            </div>
            <form method="post" action="<?= h(url('/gestor/prestacao-contas/remover-assinatura')) ?>" class="js-prevent-double-submit" data-confirm="Confirmar a remoção das assinaturas selecionadas?">
                <?= csrf_field() ?>
                <?= idempotency_field('gestor.prestacao_contas.remove_signature.' . (int) ($documentIdentity['entity_id'] ?? 0)) ?>
                <?php $renderFilterFields($filters); ?>
                <label class="dti-primary-signer">
                    <span>Assinatura principal</span>
                    <strong><?= h($valueOrDash($signature['nome'] ?? current_user()['nome'] ?? '')) ?></strong>
                    <small>
                        <input type="checkbox" name="remover_assinatura_principal" value="1">
                        Remover assinatura principal e cancelar todos os responsáveis pela conferência deste documento.
                    </small>
                </label>

                <div class="dti-cosigner-panel">
                    <span>Responsáveis pela conferência</span>
                    <?php if ($coSignatureRequests !== []): ?>
                        <?php foreach ($coSignatureRequests as $solicitacao): ?>
                            <label class="dti-primary-signer">
                                <strong><?= h($valueOrDash($solicitacao['coautor_nome'] ?? '')) ?></strong>
                                <small>
                                    <input type="checkbox" name="remover_coassinaturas[]" value="<?= h((int) ($solicitacao['id'] ?? 0)) ?>">
                                    <?= h(['pendente' => 'Pendente', 'autorizado' => 'Autorizado', 'negado' => 'Não autorizado'][$solicitacao['status'] ?? ''] ?? '-') ?>
                                </small>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <small>Não há responsáveis pela conferência ativos neste documento.</small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="danger-button" data-loading-text="Removendo...">Remover selecionados</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="records-summary-grid delivery-summary-grid no-print">
        <article class="records-summary-card">
            <span>Entregas filtradas</span>
            <strong><?= h($summary['total_entregas'] ?? 0) ?></strong>
            <small>Registros considerados.</small>
        </article>
        <article class="records-summary-card">
            <span>Famílias atendidas</span>
            <strong><?= h($summary['familias_atendidas'] ?? 0) ?></strong>
            <small>Famílias únicas no filtro.</small>
        </article>
        <article class="records-summary-card">
            <span>Tipos distribuídos</span>
            <strong><?= h($summary['tipos_distribuidos'] ?? 0) ?></strong>
            <small>Materiais distintos.</small>
        </article>
        <article class="records-summary-card">
            <span>Quantidade total</span>
            <strong><?= h($formatQuantity($summary['quantidade_total'] ?? 0)) ?></strong>
            <small>Soma das quantidades.</small>
        </article>
    </section>

    <section class="records-filter-panel delivery-filter-panel accountability-filter-panel prestacao-filter-panel no-print">
        <div class="table-heading">
            <h2>Filtros inteligentes</h2>
            <span>Combine busca textual, ação, tipo de material, localidade e período.</span>
        </div>
        <form method="get" action="<?= h(url('/gestor/prestacao-contas')) ?>" class="accountability-filter-form prestacao-filter-form">
            <div class="accountability-filter-main prestacao-filter-main">
                <label class="field styled-field prestacao-filter-field prestacao-filter-field-wide">
                    <span>Buscar</span>
                    <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" list="prestacao-busca-list" placeholder="Nome, CPF, comprovante ou protocolo">
                    <datalist id="prestacao-busca-list">
                        <?php foreach ($documentDetails as $item): ?>
                            <option value="<?= h($item['beneficiario_nome'] ?? '') ?>"></option>
                            <option value="<?= h($item['beneficiario_cpf'] ?? '') ?>"></option>
                            <option value="<?= h($item['protocolo'] ?? '') ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </label>

                <label class="field styled-field smart-search-field prestacao-filter-field prestacao-filter-field-wide">
                    <span>Ação</span>
                    <input type="search" name="acao_busca" value="<?= h(($filters['acao_busca'] ?? '') !== '' ? $filters['acao_busca'] : $selectedActionLabel) ?>" list="prestacao-acoes-list" placeholder="Digite para buscar a ação" data-smart-search data-smart-target="prestacao_acao_id" autocomplete="off">
                    <input type="hidden" name="acao_id" value="<?= h($filters['acao_id'] ?? '') ?>" data-smart-hidden="prestacao_acao_id">
                    <datalist id="prestacao-acoes-list">
                        <?php foreach ($acoes ?? [] as $acao): ?>
                            <option value="<?= h($actionOptionLabel($acao)) ?>" data-id="<?= h($acao['id']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </label>

                <label class="field styled-field smart-search-field prestacao-filter-field prestacao-filter-field-medium">
                    <span>Tipo de material</span>
                    <input type="search" name="tipo_ajuda_busca" value="<?= h(($filters['tipo_ajuda_busca'] ?? '') !== '' ? $filters['tipo_ajuda_busca'] : $selectedTypeLabel) ?>" list="prestacao-tipos-list" placeholder="Digite para buscar o material" data-smart-search data-smart-target="prestacao_tipo_ajuda_id" autocomplete="off">
                    <input type="hidden" name="tipo_ajuda_id" value="<?= h($filters['tipo_ajuda_id'] ?? '') ?>" data-smart-hidden="prestacao_tipo_ajuda_id">
                    <datalist id="prestacao-tipos-list">
                        <?php foreach ($tipos ?? [] as $tipo): ?>
                            <option value="<?= h($tipo['nome'] . ' (' . $tipo['unidade_medida'] . ')') ?>" data-id="<?= h($tipo['id']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </label>

                <label class="field styled-field prestacao-filter-field prestacao-filter-field-medium">
                    <span>Localidade, bairro ou comunidade</span>
                    <input type="search" name="localidade_busca" value="<?= h($filters['localidade_busca'] ?? '') ?>" placeholder="Digite localidade, bairro ou comunidade">
                </label>

                <label class="field styled-field prestacao-filter-field prestacao-filter-field-medium">
                    <span>Etapa do documento</span>
                    <select name="status_operacional">
                        <option value="" <?= ($filters['status_operacional'] ?? '') === '' ? 'selected' : '' ?>>Registradas e entregues</option>
                        <option value="registrado" <?= ($filters['status_operacional'] ?? '') === 'registrado' ? 'selected' : '' ?>>Registradas</option>
                        <option value="entregue" <?= ($filters['status_operacional'] ?? '') === 'entregue' ? 'selected' : '' ?>>Entregues</option>
                    </select>
                </label>
            </div>

            <div class="accountability-filter-side prestacao-filter-side">
                <div class="accountability-date-range">
                    <label class="field styled-field prestacao-filter-field prestacao-filter-field-date">
                        <span>Início</span>
                        <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
                    </label>

                    <label class="field styled-field prestacao-filter-field prestacao-filter-field-date">
                        <span>Fim</span>
                        <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
                    </label>
                </div>

                <div class="records-filter-actions delivery-history-filter-actions accountability-filter-actions">
                    <button type="submit" class="primary-button">Filtrar</button>
                    <a class="secondary-button" href="<?= h(url('/gestor/prestacao-contas')) ?>">Limpar</a>
                </div>
            </div>
        </form>

        <?php if ($activeFilters !== []): ?>
            <div class="records-active-filters" aria-label="Filtros ativos">
                <?php foreach ($activeFilters as $key => $value): ?>
                    <span><?= h(($filterLabels[$key] ?? $key) . ': ' . $value) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!$hasAppliedFilters && !$embedDocument): ?>
        <section class="action-empty-panel records-empty-panel no-print">
            <h2>Aplique um filtro para gerar o documento</h2>
            <p>Use pelo menos um filtro operacional, como ação, tipo de material, localidade, período ou busca textual. A prestação de contas não é carregada automaticamente para evitar documento amplo sem recorte.</p>
        </section>
    <?php else: ?>
        <?php if ($signature !== null && !$printReady && !$embedDocument): ?>
            <section class="signature-flow-panel no-print">
                <div>
                    <span class="eyebrow">Fluxo de coassinatura</span>
                    <h2>Impressão aguardando autorização</h2>
                    <p><?= h((int) ($coSignatureStatus['pendentes'] ?? 0)) ?> pendente(s), <?= h((int) ($coSignatureStatus['autorizados'] ?? 0)) ?> autorizado(s), <?= h((int) ($coSignatureStatus['negados'] ?? 0)) ?> não autorizado(s).</p>
                </div>
                <a class="secondary-button signature-flow-action" href="<?= h(url('/assinaturas')) ?>">Acompanhar assinaturas</a>
            </section>
        <?php endif; ?>

        <?php if ($signature !== null && !$printReady && !$embedDocument): ?>
            <div class="print-blocked-message print-only">
                Impressão bloqueada. Este documento possui coassinatura pendente ou não autorizada.
            </div>
        <?php endif; ?>

        <?php if ($signature === null && !$embedDocument): ?>
            <section class="dti-signature-setup no-print" id="prestacao-signature-form">
                <div>
                    <span class="eyebrow">Assinatura digital conjunta</span>
                    <h2>Assinar prestação de contas</h2>
                    <p>O usuário logado assina primeiro. Responsáveis pela conferência são opcionais; pesquise e selecione outros usuários apenas quando o documento precisar de conferência conjunta.</p>
                </div>
                <form method="post" action="<?= h(url('/gestor/prestacao-contas/assinar')) ?>" class="js-prevent-double-submit">
                    <?= csrf_field() ?>
                    <?= idempotency_field('gestor.prestacao_contas.sign.' . (int) ($documentIdentity['entity_id'] ?? 0)) ?>
                    <?php $renderFilterFields($filters); ?>
                    <div class="dti-primary-signer">
                        <span>1. Assinante principal</span>
                        <strong><?= h(current_user()['nome'] ?? 'Usuário logado') ?></strong>
                        <small><?= h(current_user()['cpf'] ?? '') ?><?= !empty(current_user()['graduacao']) ? ' - ' . h(current_user()['graduacao']) : '' ?><?= !empty(current_user()['nome_guerra']) ? ' ' . h(current_user()['nome_guerra']) : '' ?><?= !empty(current_user()['matricula_funcional']) ? ' | MF ' . h(current_user()['matricula_funcional']) : '' ?></small>
                    </div>
                    <div class="dti-cosigner-panel">
                        <span>2. Responsáveis pela conferência</span>
                        <?php if (($signatureUsers ?? []) === []): ?>
                            <div class="dti-empty">Nenhum outro usuário ativo disponível para coassinar.</div>
                        <?php else: ?>
                            <div class="dti-cosigner-picker" data-dti-cosigner-picker>
                                <label class="field smart-search-field">
                                    <span>Buscar usuário</span>
                                    <input type="search" placeholder="Digite nome, CPF, MF, graduação ou nome de guerra" autocomplete="off" data-dti-cosigner-search>
                                </label>
                                <div class="dti-cosigner-selected" data-dti-cosigner-selected aria-live="polite">
                                    <span>Nenhum responsável pela conferência selecionado.</span>
                                </div>
                                <div class="dti-cosigner-hint" data-dti-cosigner-status>Digite para buscar usuários do sistema.</div>
                                <div class="dti-cosigner-options" data-dti-cosigner-options>
                                <?php foreach ($signatureUsers ?? [] as $usuarioAssinante): ?>
                                    <?php
                                    $assinanteLabel = trim((string) $usuarioAssinante['nome']);
                                    $assinanteMeta = trim(
                                        (string) ($usuarioAssinante['cpf'] ?? '')
                                        . (!empty($usuarioAssinante['graduacao']) ? ' - ' . (string) $usuarioAssinante['graduacao'] : '')
                                        . (!empty($usuarioAssinante['nome_guerra']) ? ' ' . (string) $usuarioAssinante['nome_guerra'] : '')
                                        . (!empty($usuarioAssinante['matricula_funcional']) ? ' | MF ' . (string) $usuarioAssinante['matricula_funcional'] : '')
                                    );
                                    $assinanteSearch = trim($assinanteLabel . ' ' . $assinanteMeta . ' ' . ($usuarioAssinante['email'] ?? ''));
                                    ?>
                                    <button type="button" data-dti-cosigner-option data-id="<?= h($usuarioAssinante['id']) ?>" data-label="<?= h($assinanteLabel) ?>" data-meta="<?= h($assinanteMeta) ?>" data-search="<?= h($assinanteSearch) ?>">
                                        <span>
                                            <strong><?= h($assinanteLabel) ?></strong>
                                            <small><?= h($assinanteMeta) ?></small>
                                        </span>
                                    </button>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="primary-button" data-loading-text="Assinando...">Assinar documento</button>
                </form>
            </section>
        <?php endif; ?>

    <section class="accountability-document dti-document" aria-label="Documento de prestação de contas">
        <article class="dti-page accountability-page-sheet">
            <header class="dti-institutional-header">
                <img src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA">
                <div>
                    <strong>Corpo de Bombeiros Militar do Pará</strong>
                    <span>Coordenadoria Estadual de Proteção e Defesa Civil</span>
                    <h2>Prestação de contas de ajuda humanitária</h2>
                </div>
            </header>

            <section class="dti-section accountability-section">
                <h3>1. Dados do solicitante</h3>
                <div class="accountability-table-wrap">
                <table class="dti-table accountability-info-table">
                    <tbody>
                        <tr>
                            <th>Município</th>
                            <td class="accountability-info-value accountability-long-value"><?= $softBreak($municipioLabel) ?></td>
                            <th>Data da entrega</th>
                            <td class="accountability-info-value"><?= h($documentDate) ?></td>
                        </tr>
                        <tr>
                            <th>Responsável pela distribuição</th>
                            <td class="accountability-info-value accountability-long-value"><?= $softBreak($currentUser['nome'] ?? '') ?></td>
                            <th>Telefone</th>
                            <td class="accountability-info-value"><?= h($valueOrDash($currentUser['telefone'] ?? '')) ?></td>
                        </tr>
                        <tr>
                            <th>E-mail</th>
                            <td class="accountability-info-value accountability-email-value"><?= $softBreak($currentUser['email'] ?? '') ?></td>
                            <th>Tipo de material distribuído</th>
                            <td class="accountability-info-value accountability-long-value"><?= $softBreak($materialLabel) ?></td>
                        </tr>
                        <tr>
                            <th>Total de famílias</th>
                            <td class="accountability-info-value"><?= h((int) ($documentContext['total_familias'] ?? 0)) ?></td>
                            <th>Localidade, bairro ou comunidade</th>
                            <td class="accountability-info-value accountability-long-value"><?= $softBreak($localidadeLabel) ?></td>
                        </tr>
                        <tr>
                            <th>Período filtrado</th>
                            <td class="accountability-info-value"><?= h($periodo) ?></td>
                            <th>Código do documento</th>
                            <td class="accountability-info-value accountability-code-value"><?= $softBreak($documentCode) ?></td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </section>

            <section class="dti-section accountability-section">
                <h3>2. Dados sobre a distribuição</h3>
                <div class="accountability-table-wrap">
                <table class="dti-table accountability-list-table">
                    <thead>
                        <tr>
                            <th>N.</th>
                            <th>Nome do beneficiário</th>
                            <th>CPF</th>
                            <th>Etapa</th>
                            <th>Quantidade recebida</th>
                            <th>Assinatura</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentDetails as $index => $item): ?>
                            <tr>
                                <td><?= h(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></td>
                                <td><?= h($valueOrDash($item['beneficiario_nome'] ?? '')) ?></td>
                                <td><?= h($valueOrDash($item['beneficiario_cpf'] ?? '')) ?></td>
                                <td><?= ((string) ($item['status_operacional'] ?? 'entregue')) === 'registrado' ? 'Registrada' : 'Entregue' ?></td>
                                <td><?= h($formatQuantity($item['quantidade_total'] ?? 0)) ?> <?= h($item['unidade_medida'] ?? '') ?></td>
                                <td class="manual-signature-cell"></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($documentDetails === []): ?>
                            <tr>
                                <td colspan="6" class="dti-empty">Nenhuma entrega encontrada para os filtros informados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </section>

            <footer class="dti-page-footer">
                <span><?= h($documentCode) ?></span>
                <span>Relação nominal | Registros <?= h($documentTotalRecords > 0 ? $documentFirstRecord . '-' . $documentLastRecord : '0') ?> de <?= h($documentTotalRecords) ?></span>
            </footer>
        </article>

        <article class="dti-page accountability-page-sheet accountability-signature-sheet">
            <header class="dti-page-heading">
                <strong>Prestação de contas de ajuda humanitária</strong>
                <span><?= h($documentCode) ?></span>
            </header>

            <section class="dti-section dti-signature-section accountability-signature-section">
                <h3>3. Assinaturas</h3>
                <?php if ($signature === null): ?>
                    <div class="dti-signature-pending">
                        Documento ainda não assinado. Use a ação "Assinar documento" na prévia antes da impressão oficial.
                    </div>
                <?php else: ?>
                    <div class="dti-signature-card">
                        <img class="dti-signature-logo" src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA">
                        <div>
                            <span>Visto do responsável pela distribuição</span>
                            <strong><?= h($valueOrDash($primarySigner['nome'] ?? ($signature['nome'] ?? ''))) ?></strong>
                            <p>
                                <?= h($valueOrDash($primarySigner['graduacao'] ?? ($signature['graduacao'] ?? ''))) ?>
                                <?php if (!empty($primarySigner['nome_guerra'] ?? ($signature['nome_guerra'] ?? ''))): ?>
                                    - <?= h($primarySigner['nome_guerra'] ?? $signature['nome_guerra']) ?>
                                <?php endif; ?>
                            </p>
                            <p>CPF: <?= h($valueOrDash($primarySigner['cpf'] ?? ($signature['cpf'] ?? ''))) ?> | Data/hora: <?= h($signedAtText) ?></p>
                            <?php if (!empty($primarySigner['matricula_funcional'] ?? ($signature['matricula_funcional'] ?? ''))): ?>
                                <p>MF: <?= h($primarySigner['matricula_funcional'] ?? $signature['matricula_funcional']) ?></p>
                            <?php endif; ?>
                            <p>Hash: <?= h($signatureHash !== '' ? substr($signatureHash, 0, 16) . '...' . substr($signatureHash, -12) : '-') ?></p>
                        </div>
                    </div>
                    <?php if (count($signatureSigners) > 1): ?>
                        <div class="dti-cosigner-list">
                            <span>Responsáveis pela conferência</span>
                            <?php foreach (array_slice($signatureSigners, 1) as $assinante): ?>
                                <div>
                                    <strong><?= h($valueOrDash($assinante['nome'] ?? '')) ?></strong>
                                    <p>
                                        CPF: <?= h($valueOrDash($assinante['cpf'] ?? '')) ?>
                                        <?php if (!empty($assinante['graduacao'])): ?>
                                            | <?= h($assinante['graduacao']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($assinante['nome_guerra'])): ?>
                                            - <?= h($assinante['nome_guerra']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($assinante['matricula_funcional'])): ?>
                                            | MF: <?= h($assinante['matricula_funcional']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($coSignatureRequests !== [] && !$printReady): ?>
                        <div class="dti-cosigner-list no-print">
                            <span>Status dos responsáveis pela conferência</span>
                            <?php foreach ($coSignatureRequests as $solicitacao): ?>
                                <div>
                                    <strong><?= h($valueOrDash($solicitacao['coautor_nome'] ?? '')) ?></strong>
                                    <p><?= h(['pendente' => 'Pendente', 'autorizado' => 'Autorizado', 'negado' => 'Não autorizado'][$solicitacao['status'] ?? ''] ?? '-') ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <footer class="dti-page-footer">
                <span><?= h($documentCode) ?></span>
                <span>Folha 2 de 2</span>
            </footer>
        </article>
    </section>
    <?php endif; ?>

    <?php if ($hasAppliedFilters && $totalPages > 1 && !$embedDocument && $documentTotalRecords < $totalRecords): ?>
        <nav class="records-pagination delivery-pagination no-print" aria-label="Paginação da prestação de contas">
            <a class="secondary-button <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= h($pageUrl(max(1, $page - 1))) ?>">Anterior</a>
            <div class="pagination-pages">
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                if ($endPage - $startPage < 4) {
                    $startPage = max(1, min($startPage, $endPage - 4));
                    $endPage = min($totalPages, max($endPage, $startPage + 4));
                }
                ?>
                <?php if ($startPage > 1): ?>
                    <a href="<?= h($pageUrl(1)) ?>">1</a>
                    <?php if ($startPage > 2): ?><span>...</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($itemPage = $startPage; $itemPage <= $endPage; $itemPage++): ?>
                    <a class="<?= $itemPage === $page ? 'is-active' : '' ?>" href="<?= h($pageUrl($itemPage)) ?>"><?= h($itemPage) ?></a>
                <?php endfor; ?>
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?><span>...</span><?php endif; ?>
                    <a href="<?= h($pageUrl($totalPages)) ?>"><?= h($totalPages) ?></a>
                <?php endif; ?>
            </div>
            <a class="secondary-button <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h($pageUrl(min($totalPages, $page + 1))) ?>">Próxima</a>
        </nav>
    <?php endif; ?>
</section>

<?php if (!$embedDocument): ?>
<script>
    (function () {
        function isMobilePrintContext() {
            return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
        }

        function fallbackToPrintPage(link) {
            var opened = window.open(link.href, '_blank', 'noopener');

            if (!opened) {
                window.location.href = link.href;
            }
        }

        document.querySelectorAll('[data-prestacao-print-url]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                var documentUrl = link.getAttribute('data-prestacao-print-url') || '';

                if (!documentUrl || isMobilePrintContext()) {
                    return;
                }

                event.preventDefault();

                var frame = document.querySelector('[data-prestacao-print-frame]');
                if (!frame) {
                    frame = document.createElement('iframe');
                    frame.setAttribute('data-prestacao-print-frame', '1');
                    frame.setAttribute('aria-hidden', 'true');
                    frame.style.position = 'fixed';
                    frame.style.right = '0';
                    frame.style.bottom = '0';
                    frame.style.width = '0';
                    frame.style.height = '0';
                    frame.style.border = '0';
                    frame.style.opacity = '0';
                    document.body.appendChild(frame);
                }

                frame.onload = function () {
                    try {
                        frame.contentWindow.focus();
                        frame.contentWindow.print();
                    } catch (error) {
                        fallbackToPrintPage(link);
                    }
                };

                frame.src = documentUrl;
            });
        });
    })();
</script>
<?php endif; ?>
