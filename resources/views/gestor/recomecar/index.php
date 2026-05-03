<?php
$summary = $indicators ?? [];
$filters = $filters ?? [];
$hasAppliedFilters = (bool) ($hasAppliedFilters ?? false);
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'total' => count($details ?? []), 'per_page' => 10];
$documentContext = $documentContext ?? [];
$generatedAtText = $generatedAt instanceof DateTimeInterface ? $generatedAt->format('d/m/Y H:i') : date('d/m/Y H:i');
$documentDate = $generatedAt instanceof DateTimeInterface ? $generatedAt->format('d/m/Y') : date('d/m/Y');
$documentCode = 'REC-' . ($generatedAt instanceof DateTimeInterface ? $generatedAt->format('Ymd-His') : date('Ymd-His')) . '-' . strtoupper(substr(hash('sha256', json_encode($filters)), 0, 8));
$signedAtText = $signature !== null && !empty($signature['signed_at']) ? date('d/m/Y H:i', strtotime((string) $signature['signed_at'])) : '';
$signatureHash = (string) ($signature['hash'] ?? '');
$signatureSigners = is_array($signature['assinantes'] ?? null) ? $signature['assinantes'] : [];
$coSignatureStatus = is_array($signature['coassinatura_status'] ?? null) ? $signature['coassinatura_status'] : ($coSignatureStatus ?? ['total' => 0, 'pendentes' => 0, 'autorizados' => 0, 'negados' => 0, 'impressao_liberada' => true, 'solicitacoes' => []]);
$printReady = $signature !== null && (bool) ($signature['impressao_liberada'] ?? $coSignatureStatus['impressao_liberada'] ?? true);
$embedDocument = (bool) ($embedDocument ?? false);
$currentUserId = (int) (current_user()['id'] ?? 0);
$signaturePrincipalId = (int) ($signature['usuario_id'] ?? 0);
$canManageSignature = $signature !== null && $signaturePrincipalId > 0 && $signaturePrincipalId === $currentUserId;
$canSignPaymentDocument = ($filters['aptidao'] ?? 'apta') === 'apta';
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
$periodo = 'Todo o período';

if (($filters['data_inicio'] ?? '') !== '' || ($filters['data_fim'] ?? '') !== '') {
    $periodo = ($filters['data_inicio'] ?: 'início') . ' a ' . ($filters['data_fim'] ?: 'hoje');
}

$valueOrDash = static function (mixed $value): string {
    $text = trim((string) $value);

    return $text !== '' ? $text : '-';
};

$beneficiaryCpfDigits = static function (mixed $value) use ($valueOrDash): string {
    $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

    if ($digits === '') {
        return $valueOrDash($value);
    }

    return $digits;
};

$softBreak = static function (mixed $value) use ($valueOrDash): string {
    $text = $valueOrDash($value);

    if ($text === '-') {
        return h($text);
    }

    return str_replace(['@', '.', '/', '-'], ['<wbr>@', '.<wbr>', '/<wbr>', '-<wbr>'], h($text));
};

$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp !== false ? date('d/m/Y', $timestamp) : '-';
};

$sexLabel = static function (mixed $value): string {
    return [
        'feminino' => 'Feminino',
        'masculino' => 'Masculino',
        'outro' => 'Outro',
        'nao_informado' => 'Não informado',
    ][(string) $value] ?? '-';
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
    if ((string) ($filters['acao_id'] ?? '') === (string) $acao['id']) {
        $selectedActionLabel = $actionOptionLabel($acao);
        break;
    }
}

$activeFilters = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '' && (string) $value !== 'apta');
unset($activeFilters['acao_id']);
$filterLabels = [
    'q' => 'Busca',
    'acao_busca' => 'Ação',
    'localidade_busca' => 'Localidade/bairro',
    'aptidao' => 'Situação',
    'status_entrega' => 'Entrega Recomeçar',
    'data_inicio' => 'Início',
    'data_fim' => 'Fim',
];

$pageUrl = static function (int $targetPage) use ($filters): string {
    $params = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
    $params['pagina'] = max(1, $targetPage);

    return url('/gestor/recomecar') . '?' . http_build_query($params);
};

$renderFilterFields = static function (array $filters): void {
    foreach ($filters as $key => $value) {
        if ((string) $value === '') {
            continue;
        }
        echo '<input type="hidden" name="' . h((string) $key) . '" value="' . h((string) $value) . '">' . PHP_EOL;
    }
};

$deliveryStatusLabel = static function (mixed $status): string {
    return (string) $status === 'entregue' ? 'Recomeçar entregue' : 'Sem Recomeçar';
};

$municipioLabel = $valueOrDash($documentContext['municipios'] ?? '');
$localidadeLabel = $valueOrDash(trim(($documentContext['localidades'] ?? '') . (($documentContext['bairros'] ?? '') !== '' ? ' / ' . $documentContext['bairros'] : '')));
?>

<style media="print">
    @page {
        size: A4 landscape;
        margin: 12mm;
    }
</style>

<section class="records-page accountability-page recomecar-page <?= $signature !== null && !$printReady ? 'is-print-blocked' : '' ?>">
    <?php if (!$embedDocument): ?>
    <header class="dashboard-header deliveries-header accountability-header no-print">
        <div>
            <span class="eyebrow">Gestão operacional</span>
            <h1>Programa Recomeçar</h1>
            <p>Gere o documento nominal de famílias aptas ao pagamento de um salário mínimo por família.</p>
        </div>
        <?php if ($hasAppliedFilters && $signature === null && $canSignPaymentDocument): ?>
            <form method="post" action="<?= h(url('/gestor/recomecar/assinar')) ?>" class="inline-form js-prevent-double-submit">
                <?= csrf_field() ?>
                <?= idempotency_field('gestor.recomecar.sign.' . (int) ($documentIdentity['entity_id'] ?? 0)) ?>
                <?php $renderFilterFields($filters); ?>
                <button type="submit" class="primary-button" data-loading-text="Assinando...">Assinar documento</button>
            </form>
            <a class="secondary-button" href="#recomecar-signature-form">Assinatura conjunta</a>
        <?php elseif ($hasAppliedFilters && $signature === null): ?>
            <span class="limit-reached-pill">Consulta sem assinatura</span>
        <?php elseif ($hasAppliedFilters): ?>
            <span class="limit-reached-pill"><?= $printReady ? 'Documento assinado' : 'Aguardando conferência' ?></span>
            <?php if ($canManageSignature): ?>
                <form method="post" action="<?= h(url('/gestor/recomecar/remover-assinatura')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Confirmar a remoção da assinatura deste documento?">
                    <?= csrf_field() ?>
                    <?= idempotency_field('gestor.recomecar.remove_signature.' . (int) ($documentIdentity['entity_id'] ?? 0)) ?>
                    <?php $renderFilterFields($filters); ?>
                    <button type="submit" class="danger-button" data-loading-text="Removendo...">Remover assinatura</button>
                </form>
            <?php endif; ?>
            <?php if ($printReady): ?>
                <button type="button" class="primary-button" onclick="window.print()">Imprimir documento</button>
            <?php else: ?>
                <span class="limit-reached-pill">Impressão bloqueada</span>
            <?php endif; ?>
        <?php endif; ?>
    </header>

    <section class="records-summary-grid delivery-summary-grid no-print">
        <article class="records-summary-card">
            <span>Famílias no recorte</span>
            <strong><?= h($summary['total_familias'] ?? 0) ?></strong>
            <small>Cadastros considerados pelos filtros.</small>
        </article>
        <article class="records-summary-card">
            <span>Aptas</span>
            <strong><?= h($summary['familias_aptas'] ?? 0) ?></strong>
            <small>Recebem 1 salário mínimo vigente.</small>
        </article>
        <article class="records-summary-card">
            <span>Inaptas</span>
            <strong><?= h($summary['familias_inaptas'] ?? 0) ?></strong>
            <small>Imóvel não atingido ou renda acima de 3 salários.</small>
        </article>
        <article class="records-summary-card">
            <span>Benefícios previstos</span>
            <strong><?= h($summary['familias_aptas'] ?? 0) ?></strong>
            <small>Quantidade de salários mínimos.</small>
        </article>
    </section>

    <section class="records-filter-panel delivery-filter-panel accountability-filter-panel recomecar-filter-panel no-print">
        <div class="table-heading">
            <h2>Filtros inteligentes</h2>
            <span>Combine ação, localidade, período, busca textual e situação no programa.</span>
        </div>
        <form method="get" action="<?= h(url('/gestor/recomecar')) ?>" class="accountability-filter-form recomecar-filter-form">
            <div class="accountability-filter-main recomecar-filter-main">
                <label class="field styled-field recomecar-filter-field recomecar-filter-field-wide">
                    <span>Buscar</span>
                    <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" placeholder="Nome, CPF ou protocolo">
                </label>

                <label class="field styled-field smart-search-field recomecar-filter-field recomecar-filter-field-wide">
                    <span>Ação</span>
                    <input type="search" name="acao_busca" value="<?= h(($filters['acao_busca'] ?? '') !== '' ? $filters['acao_busca'] : $selectedActionLabel) ?>" list="recomecar-acoes-list" placeholder="Digite para buscar a ação" data-smart-search data-smart-target="recomecar_acao_id" autocomplete="off">
                    <input type="hidden" name="acao_id" value="<?= h($filters['acao_id'] ?? '') ?>" data-smart-hidden="recomecar_acao_id">
                    <datalist id="recomecar-acoes-list">
                        <?php foreach ($acoes ?? [] as $acao): ?>
                            <option value="<?= h($actionOptionLabel($acao)) ?>" data-id="<?= h($acao['id']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </label>

                <label class="field styled-field recomecar-filter-field recomecar-filter-field-medium">
                    <span>Localidade, bairro ou comunidade</span>
                    <input type="search" name="localidade_busca" value="<?= h($filters['localidade_busca'] ?? '') ?>" placeholder="Digite localidade, bairro ou comunidade">
                </label>

                <label class="field styled-field recomecar-filter-field recomecar-filter-field-compact">
                    <span>Situação no programa</span>
                    <select name="aptidao">
                        <option value="apta" <?= ($filters['aptidao'] ?? 'apta') === 'apta' ? 'selected' : '' ?>>Aptas para pagamento</option>
                        <option value="inapta" <?= ($filters['aptidao'] ?? '') === 'inapta' ? 'selected' : '' ?>>Inaptas</option>
                        <option value="todas" <?= ($filters['aptidao'] ?? '') === 'todas' ? 'selected' : '' ?>>Todas</option>
                    </select>
                </label>

                <label class="field styled-field recomecar-filter-field recomecar-filter-field-compact">
                    <span>Entrega do Recomeçar</span>
                    <select name="status_entrega">
                        <option value="" <?= ($filters['status_entrega'] ?? '') === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="entregue" <?= ($filters['status_entrega'] ?? '') === 'entregue' ? 'selected' : '' ?>>Recomeçar entregue</option>
                    </select>
                </label>
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
                    <a class="secondary-button" href="<?= h(url('/gestor/recomecar')) ?>">Limpar</a>
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
            <p>Use pelo menos um filtro operacional. Por segurança, o documento do Programa Recomeçar não carrega automaticamente sem recorte.</p>
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

        <?php if ($hasAppliedFilters && !$canSignPaymentDocument && !$embedDocument): ?>
            <section class="signature-flow-panel no-print">
                <div>
                    <span class="eyebrow">Regra de pagamento</span>
                    <h2>Assinatura disponível apenas para famílias aptas</h2>
                    <p>Altere o filtro "Situação no programa" para "Aptas para pagamento" antes de assinar o documento.</p>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($signature === null && $canSignPaymentDocument && !$embedDocument): ?>
            <section class="dti-signature-setup no-print" id="recomecar-signature-form">
                <div>
                    <span class="eyebrow">Assinatura digital conjunta</span>
                    <h2>Assinar documento</h2>
                    <p>O usuário logado assina primeiro. Responsáveis pela conferência são opcionais e devem ser gestor ou administrador.</p>
                </div>
                <form method="post" action="<?= h(url('/gestor/recomecar/assinar')) ?>" class="js-prevent-double-submit">
                    <?= csrf_field() ?>
                    <?= idempotency_field('gestor.recomecar.sign.' . (int) ($documentIdentity['entity_id'] ?? 0)) ?>
                    <?php $renderFilterFields($filters); ?>
                    <div class="dti-primary-signer">
                        <span>1. Assinante principal</span>
                        <strong><?= h(current_user()['nome'] ?? 'Usuário logado') ?></strong>
                        <small><?= h(current_user()['cpf'] ?? '') ?><?= !empty(current_user()['graduacao']) ? ' - ' . h(current_user()['graduacao']) : '' ?><?= !empty(current_user()['nome_guerra']) ? ' ' . h(current_user()['nome_guerra']) : '' ?><?= !empty(current_user()['matricula_funcional']) ? ' | MF ' . h(current_user()['matricula_funcional']) : '' ?></small>
                    </div>
                    <div class="dti-cosigner-panel">
                        <span>2. Responsáveis pela conferência</span>
                        <?php if (($signatureUsers ?? []) === []): ?>
                            <div class="dti-empty">Nenhum gestor ou administrador ativo disponível para coassinar.</div>
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
                                    $assinanteMeta = trim((string) ($usuarioAssinante['cpf'] ?? '') . ' - ' . (string) ($usuarioAssinante['perfil'] ?? ''));
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

    <div class="recomecar-document-viewport" data-recomecar-document-viewport>
    <section class="accountability-document dti-document recomecar-document" aria-label="Documento do Programa Recomeçar" data-recomecar-document>
        <article class="dti-page accountability-page-sheet">
            <header class="dti-institutional-header">
                <img src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA">
                <div>
                    <strong>Corpo de Bombeiros Militar do Pará</strong>
                    <span>Coordenadoria Estadual de Proteção e Defesa Civil</span>
                    <h2>Programa Recomeçar - relação de famílias aptas</h2>
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
                            <th>Data do documento</th>
                            <td class="accountability-info-value"><?= h($documentDate) ?></td>
                        </tr>
                        <tr>
                            <th>Responsável pela geração</th>
                            <td class="accountability-info-value accountability-long-value"><?= $softBreak($currentUser['nome'] ?? '') ?></td>
                            <th>Telefone</th>
                            <td class="accountability-info-value"><?= h($valueOrDash($currentUser['telefone'] ?? '')) ?></td>
                        </tr>
                        <tr>
                            <th>E-mail</th>
                            <td class="accountability-info-value accountability-email-value"><?= $softBreak($currentUser['email'] ?? '') ?></td>
                            <th>Benefício</th>
                            <td class="accountability-info-value accountability-long-value">1 salário mínimo vigente por família apta</td>
                        </tr>
                        <tr>
                            <th>Total de famílias aptas</th>
                            <td class="accountability-info-value"><?= h((int) ($documentContext['familias_aptas'] ?? $totalRecords)) ?></td>
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
                <h3>2. Famílias para pagamento</h3>
                <div class="accountability-table-wrap">
                <table class="dti-table accountability-list-table recomecar-list-table">
                    <colgroup>
                        <col class="recomecar-col-number">
                        <col class="recomecar-col-protocol">
                        <col class="recomecar-col-name">
                        <col class="recomecar-col-sex">
                        <col class="recomecar-col-birth">
                        <col class="recomecar-col-cpf">
                        <col class="recomecar-col-rg">
                        <col class="recomecar-col-org">
                        <col class="recomecar-col-status">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>N.</th>
                            <th>Protocolo</th>
                            <th>Nome do beneficiário</th>
                            <th>Sexo</th>
                            <th>Nascimento</th>
                            <th>CPF</th>
                            <th>RG</th>
                            <th>Órgão exp.</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($details ?? [] as $index => $item): ?>
                            <?php $deliveryStatus = (string) ($item['status_entrega'] ?? 'nao_entregue'); ?>
                            <tr class="<?= $deliveryStatus === 'entregue' ? 'recomecar-row-delivered' : 'recomecar-row-pending' ?>">
                                <td><?= h(str_pad((string) ($firstRecord + $index), 2, '0', STR_PAD_LEFT)) ?></td>
                                <td><?= h($valueOrDash($item['protocolo'] ?? '')) ?></td>
                                <td><?= h($valueOrDash($item['beneficiario_nome'] ?? '')) ?></td>
                                <td><?= h($sexLabel($item['beneficiario_sexo'] ?? '')) ?></td>
                                <td><?= h($formatDate($item['beneficiario_data_nascimento'] ?? '')) ?></td>
                                <td><?= h($beneficiaryCpfDigits($item['beneficiario_cpf'] ?? '')) ?></td>
                                <td><?= h($valueOrDash($item['beneficiario_rg'] ?? '')) ?></td>
                                <td><?= h($valueOrDash($item['beneficiario_orgao_expedidor'] ?? '')) ?></td>
                                <td>
                                    <span class="recomecar-delivery-status <?= $deliveryStatus === 'entregue' ? 'is-delivered' : 'is-pending' ?>">
                                        <?= h($deliveryStatusLabel($deliveryStatus)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (($details ?? []) === []): ?>
                            <tr>
                                <td colspan="9" class="dti-empty">Nenhuma família encontrada para os filtros informados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </section>

            <footer class="dti-page-footer">
                <span><?= h($documentCode) ?></span>
                <span>Folha 1 de 2 | Registros <?= h($totalRecords > 0 ? $firstRecord . '-' . $lastRecord : '0') ?> de <?= h($totalRecords) ?></span>
            </footer>
        </article>

        <article class="dti-page accountability-page-sheet accountability-signature-sheet">
            <header class="dti-page-heading">
                <strong>Programa Recomeçar</strong>
                <span><?= h($documentCode) ?></span>
            </header>

            <section class="dti-section dti-signature-section accountability-signature-section">
                <h3>3. Assinaturas</h3>
                <?php if ($signature === null): ?>
                    <div class="dti-signature-pending">
                        Documento ainda não assinado. Use a ação "Assinar documento" antes da impressão oficial.
                    </div>
                <?php else: ?>
                    <div class="dti-signature-card">
                        <img class="dti-signature-logo" src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA">
                        <div>
                            <span>Visto do responsável pela geração</span>
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
                                    <p>CPF: <?= h($valueOrDash($assinante['cpf'] ?? '')) ?></p>
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

            <section class="dti-section">
                <h3>4. Regra de elegibilidade</h3>
                <div class="dti-signature-pending">
                    Família inapta quando o imóvel estiver marcado como não atingido ou quando a renda familiar estiver acima de 3 salários.
                </div>
            </section>

            <footer class="dti-page-footer">
                <span><?= h($documentCode) ?></span>
                <span>Folha 2 de 2</span>
            </footer>
        </article>
    </section>
    </div>
    <?php endif; ?>

    <?php if ($hasAppliedFilters && $totalPages > 1 && !$embedDocument): ?>
        <nav class="records-pagination delivery-pagination no-print" aria-label="Paginação do Programa Recomeçar">
            <a class="secondary-button <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= h($pageUrl(max(1, $page - 1))) ?>">Anterior</a>
            <div class="pagination-pages">
                <?php for ($itemPage = 1; $itemPage <= $totalPages; $itemPage++): ?>
                    <a class="<?= $itemPage === $page ? 'is-active' : '' ?>" href="<?= h($pageUrl($itemPage)) ?>"><?= h($itemPage) ?></a>
                <?php endfor; ?>
            </div>
            <a class="secondary-button <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h($pageUrl(min($totalPages, $page + 1))) ?>">Próxima</a>
        </nav>
    <?php endif; ?>
</section>

<script>
    (function () {
        var viewport = document.querySelector('[data-recomecar-document-viewport]');
        var documentNode = document.querySelector('[data-recomecar-document]');
        var baseWidth = 1123;
        var printing = false;

        if (!viewport || !documentNode) {
            return;
        }

        function resizeDocument() {
            if (printing) {
                return;
            }

            var supportsZoom = 'zoom' in documentNode.style;
            documentNode.style.transform = 'none';
            documentNode.style.zoom = '1';
            documentNode.style.width = baseWidth + 'px';

            var available = Math.max(300, viewport.clientWidth || baseWidth);
            var scale = Math.min(1, available / baseWidth);

            if (supportsZoom) {
                documentNode.style.zoom = String(scale);
                documentNode.style.marginBottom = '0';
            } else {
                documentNode.style.transformOrigin = 'top center';
                documentNode.style.transform = 'scale(' + scale + ')';
                documentNode.style.marginBottom = Math.ceil(documentNode.scrollHeight * scale - documentNode.scrollHeight) + 'px';
            }

            var rectHeight = documentNode.getBoundingClientRect ? documentNode.getBoundingClientRect().height : 0;
            var height = supportsZoom && rectHeight > 0
                ? Math.ceil(Math.min(documentNode.scrollHeight || rectHeight, rectHeight))
                : Math.ceil(documentNode.scrollHeight * scale);
            viewport.style.minHeight = height + 'px';
        }

        function preparePrint() {
            printing = true;
            document.body.classList.add('is-printing-recomecar-document');
            documentNode.style.transform = 'none';
            documentNode.style.zoom = '1';
            documentNode.style.width = '273mm';
            viewport.style.minHeight = '0';
        }

        function restoreScreen() {
            printing = false;
            document.body.classList.remove('is-printing-recomecar-document');
            resizeDocument();
        }

        window.addEventListener('load', resizeDocument);
        window.addEventListener('resize', resizeDocument);
        window.addEventListener('beforeprint', preparePrint);
        window.addEventListener('afterprint', restoreScreen);

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(resizeDocument).catch(function () {});
        }

        documentNode.querySelectorAll('img').forEach(function (image) {
            if (!image.complete) {
                image.addEventListener('load', resizeDocument, { once: true });
                image.addEventListener('error', resizeDocument, { once: true });
            }
        });

        setTimeout(resizeDocument, 250);
        setTimeout(resizeDocument, 700);
        setTimeout(resizeDocument, 1400);
    })();
</script>
