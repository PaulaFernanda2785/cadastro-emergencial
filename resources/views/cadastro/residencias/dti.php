<?php
$formatDateTime = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '-';
};
$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp !== false ? date('d/m/Y', $timestamp) : '-';
};
$valueOrDash = static function (mixed $value): string {
    $text = trim((string) $value);

    return $text !== '' ? $text : '-';
};
$yesNo = static fn (mixed $value): string => (int) $value === 1 ? 'Sim' : 'Não';
$sexLabel = static function (mixed $value): string {
    $options = [
        'feminino' => 'Feminino',
        'masculino' => 'Masculino',
        'outro' => 'Outro',
        'nao_informado' => 'Não informado',
    ];
    $key = (string) $value;

    return $options[$key] ?? '-';
};
$generatedAtText = $generatedAt instanceof DateTimeInterface ? $generatedAt->format('d/m/Y H:i') : date('d/m/Y H:i');
$signedAtText = $signature !== null ? $formatDateTime($signature['signed_at'] ?? '') : '';
$signatureHash = (string) ($signature['hash'] ?? '');
$signatureUsers = $signatureUsers ?? [];
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
$signatureSigners = is_array($signature['assinantes'] ?? null) ? $signature['assinantes'] : [];
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
$documentCode = 'DTI-' . str_pad((string) ($residencia['id'] ?? 0), 6, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(hash('sha256', (string) ($residencia['protocolo'] ?? '')), 0, 8));
$familyPages = $familias === [] ? [[]] : array_chunk($familias, 1);
$fotosResidencia = $fotosResidencia ?? [];
$fotosDocumentos = $fotosDocumentos ?? [];
$photoBlocks = [];
foreach ([
    [
        'title' => 'Fotos da residência',
        'empty' => 'Nenhuma foto da residência anexada ao cadastro.',
        'photos' => $fotosResidencia,
    ],
    [
        'title' => 'Fotos dos documentos',
        'empty' => 'Nenhuma imagem de documento anexada às famílias.',
        'photos' => $fotosDocumentos,
    ],
] as $sectionIndex => $photoSection) {
    $chunks = $photoSection['photos'] === [] ? [[]] : array_chunk($photoSection['photos'], 4);
    foreach ($chunks as $chunkIndex => $chunk) {
        $photoBlocks[] = [
            'section' => $sectionIndex + 1,
            'part' => $chunkIndex + 1,
            'total_parts' => count($chunks),
            'title' => $photoSection['title'],
            'empty' => $photoSection['empty'],
            'photos' => $chunk,
        ];
    }
}
$totalPages = 1 + count($familyPages) + count($photoBlocks);
$pageNumber = 1;
?>

<style media="print">
    @page {
        size: A4;
        margin: 12mm;
    }
</style>

<section class="records-page dti-preview-page <?= $signature !== null && !$printReady ? 'is-print-blocked' : '' ?>">
    <?php if (!$embedDocument): ?>
    <header class="action-form-header records-header no-print dti-screen-header">
        <div>
            <span class="eyebrow">Descrição Técnica de Imóvel</span>
            <h1>DTI <?= h($residencia['protocolo']) ?></h1>
            <p><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?> - <?= h($residencia['bairro_comunidade']) ?></p>
        </div>
        <div class="header-actions">
            <a class="secondary-button residence-action-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'])) ?>">Voltar para residência</a>
            <?php if ($signature === null): ?>
                <form method="post" action="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/dti/assinar')) ?>" class="inline-form js-prevent-double-submit">
                    <?= csrf_field() ?>
                    <?= idempotency_field('cadastro.residencia.dti.sign.' . $residencia['id']) ?>
                    <button type="submit" class="primary-button" data-loading-text="Assinando...">Assinar DTI</button>
                </form>
                <a class="secondary-button residence-action-button" href="#dti-signature-form">Assinatura conjunta</a>
            <?php else: ?>
                <span class="limit-reached-pill"><?= $printReady ? 'Documento assinado' : 'Aguardando coautor' ?></span>
                <?php if ($canManageSignature): ?>
                    <a class="danger-button" href="#dti-signature-removal">Gerenciar assinaturas</a>
                <?php endif; ?>
                <?php if ($printReady): ?>
                    <button type="button" class="primary-button" onclick="window.print()">Imprimir</button>
                <?php else: ?>
                    <span class="limit-reached-pill">Impressão bloqueada</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($signature !== null && !$printReady): ?>
        <section class="signature-flow-panel no-print">
            <div>
                <span class="eyebrow">Fluxo de coassinatura</span>
                <h2>Impressão aguardando autorização</h2>
                <p><?= h((int) ($coSignatureStatus['pendentes'] ?? 0)) ?> pendente(s), <?= h((int) ($coSignatureStatus['autorizados'] ?? 0)) ?> autorizado(s), <?= h((int) ($coSignatureStatus['negados'] ?? 0)) ?> não autorizado(s).</p>
            </div>
            <a class="secondary-button signature-flow-action" href="<?= h(url('/assinaturas')) ?>">Acompanhar assinaturas</a>
        </section>
    <?php endif; ?>

    <?php if ($signature !== null && $canManageSignature): ?>
        <section class="dti-signature-setup no-print" id="dti-signature-removal">
            <div>
                <span class="eyebrow">Gerenciamento de assinaturas</span>
                <h2>Remover assinaturas</h2>
                <p>Somente o assinante principal pode remover a assinatura principal ou remover coautores selecionados deste documento.</p>
            </div>
            <form method="post" action="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/dti/remover-assinatura')) ?>" class="js-prevent-double-submit" data-confirm="Confirmar a remoção das assinaturas selecionadas?">
                <?= csrf_field() ?>
                <?= idempotency_field('cadastro.residencia.dti.remove_signature.' . $residencia['id']) ?>
                <label class="dti-primary-signer">
                    <span>Assinatura principal</span>
                    <strong><?= h($valueOrDash($signature['nome'] ?? current_user()['nome'] ?? '')) ?></strong>
                    <small>
                        <input type="checkbox" name="remover_assinatura_principal" value="1">
                        Remover assinatura principal e cancelar todas as coassinaturas deste documento.
                    </small>
                </label>

                <div class="dti-cosigner-panel">
                    <span>Coautores</span>
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
                        <small>Não há coautores ativos neste documento.</small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="danger-button" data-loading-text="Removendo...">Remover selecionados</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($signature !== null && !$printReady): ?>
        <div class="print-blocked-message print-only">
            Impressão bloqueada. Este documento possui coassinatura pendente ou não autorizada.
        </div>
    <?php endif; ?>

    <?php if ($signature === null): ?>
        <section class="dti-signature-setup no-print" id="dti-signature-form">
            <div>
                <span class="eyebrow">Assinatura digital conjunta</span>
                <h2>Assinar DTI</h2>
                <p>O usuário logado assina primeiro. Coassinantes são opcionais; pesquise e selecione outros usuários apenas quando a DTI precisar de assinatura conjunta.</p>
            </div>
            <form method="post" action="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/dti/assinar')) ?>" class="js-prevent-double-submit">
                <?= csrf_field() ?>
                <?= idempotency_field('cadastro.residencia.dti.sign.' . $residencia['id']) ?>
                <div class="dti-primary-signer">
                    <span>1. Assinante principal</span>
                    <strong><?= h(current_user()['nome'] ?? 'Usuário logado') ?></strong>
                    <small><?= h(current_user()['cpf'] ?? '') ?><?= !empty(current_user()['graduacao']) ? ' - ' . h(current_user()['graduacao']) : '' ?><?= !empty(current_user()['nome_guerra']) ? ' ' . h(current_user()['nome_guerra']) : '' ?><?= !empty(current_user()['matricula_funcional']) ? ' | MF ' . h(current_user()['matricula_funcional']) : '' ?></small>
                </div>
                <div class="dti-cosigner-panel">
                    <span>2. Coassinantes do sistema</span>
                    <?php if ($signatureUsers === []): ?>
                        <div class="dti-empty">Nenhum outro usuário ativo disponível para coassinar.</div>
                    <?php else: ?>
                        <div class="dti-cosigner-picker" data-dti-cosigner-picker>
                            <label class="field smart-search-field">
                                <span>Buscar usuário</span>
                                <input type="search" placeholder="Digite nome, CPF, MF, graduação ou nome de guerra" autocomplete="off" data-dti-cosigner-search>
                            </label>
                            <div class="dti-cosigner-selected" data-dti-cosigner-selected aria-live="polite">
                                <span>Nenhum coassinante selecionado.</span>
                            </div>
                            <div class="dti-cosigner-hint" data-dti-cosigner-status>Digite para buscar usuários do sistema.</div>
                            <div class="dti-cosigner-options" data-dti-cosigner-options>
                            <?php foreach ($signatureUsers as $usuarioAssinante): ?>
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
                <button type="submit" class="primary-button" data-loading-text="Assinando...">Assinar DTI</button>
            </form>
        </section>
    <?php endif; ?>
    <?php endif; ?>

    <div class="dti-document" aria-label="Prévia da DTI">
        <article class="dti-page">
            <header class="dti-institutional-header">
                <img src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA">
                <div>
                    <strong>Corpo de Bombeiros Militar do Pará</strong>
                    <span>Coordenadoria Estadual de Proteção e Defesa Civil</span>
                    <h2>DTI - Descrição Técnica de Imóvel</h2>
                </div>
            </header>

            <section class="dti-section">
                <h3>1. Identificação do documento</h3>
                <table class="dti-table">
                    <tbody>
                        <tr>
                            <th>Código DTI</th>
                            <td><?= h($documentCode) ?></td>
                            <th>Gerado em</th>
                            <td><?= h($generatedAtText) ?></td>
                        </tr>
                        <tr>
                            <th>Protocolo</th>
                            <td><?= h($residencia['protocolo']) ?></td>
                            <th>Cadastrador</th>
                            <td><?= h($valueOrDash($residencia['cadastrador_nome'] ?? '')) ?></td>
                        </tr>
                        <tr>
                            <th>Ação</th>
                            <td colspan="3"><?= h($valueOrDash(($residencia['localidade'] ?? '') . ' - ' . ($residencia['tipo_evento'] ?? ''))) ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="dti-section">
                <h3>2. Dados do imóvel e da residência</h3>
                <table class="dti-table">
                    <tbody>
                        <tr>
                            <th>Município/UF</th>
                            <td><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?></td>
                            <th class="dti-neighborhood-label">Bairro/comunidade</th>
                            <td class="dti-wrap-value dti-neighborhood-value"><?= h($valueOrDash($residencia['bairro_comunidade'] ?? '')) ?></td>
                        </tr>
                        <tr>
                            <th>Endereço</th>
                            <td colspan="3"><?= h($valueOrDash($residencia['endereco'] ?? '')) ?></td>
                        </tr>
                        <tr>
                            <th>Complemento</th>
                            <td><?= h($valueOrDash($residencia['complemento'] ?? '')) ?></td>
                            <th>Data do cadastro</th>
                            <td><?= h($formatDateTime($residencia['data_cadastro'] ?? '')) ?></td>
                        </tr>
                        <tr>
                            <th>Tipo de imóvel</th>
                            <td><?= h(residencia_imovel_label($residencia['imovel'] ?? null)) ?></td>
                            <th>Condição</th>
                            <td><?= h(residencia_condicao_label($residencia['condicao_residencia'] ?? null)) ?></td>
                        </tr>
                        <tr>
                            <th>Latitude</th>
                            <td><?= h($valueOrDash($residencia['latitude'] ?? '')) ?></td>
                            <th>Longitude</th>
                            <td><?= h($valueOrDash($residencia['longitude'] ?? '')) ?></td>
                        </tr>
                        <tr>
                            <th>Famílias informadas</th>
                            <td><?= h((int) ($residencia['quantidade_familias'] ?? 0)) ?></td>
                            <th>Famílias cadastradas</th>
                            <td><?= h(count($familias)) ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <footer class="dti-page-footer">
                <span><?= h($documentCode) ?></span>
                <span>Folha <?= h($pageNumber++) ?> de <?= h($totalPages) ?></span>
            </footer>
        </article>

        <?php foreach ($familyPages as $familyPageIndex => $familiasPagina): ?>
        <article class="dti-page">
            <header class="dti-page-heading">
                <strong>DTI - Descrição Técnica de Imóvel</strong>
                <span><?= h($residencia['protocolo']) ?></span>
            </header>

            <section class="dti-section">
                <h3>3. Famílias vinculadas à residência<?= count($familyPages) > 1 ? ' - parte ' . h($familyPageIndex + 1) : '' ?></h3>
                <?php if ($familiasPagina === []): ?>
                    <div class="dti-empty">Nenhuma família vinculada ao cadastro da residência.</div>
                <?php else: ?>
                    <?php foreach ($familiasPagina as $localFamilyIndex => $familia): ?>
                        <?php
                        $index = $familyPageIndex + $localFamilyIndex;
                        $vulnerabilidades = array_values(array_filter([
                            (int) ($familia['possui_criancas'] ?? 0) === 1 ? 'Crianças' : '',
                            (int) ($familia['possui_idosos'] ?? 0) === 1 ? 'Idosos' : '',
                            (int) ($familia['possui_pcd'] ?? 0) === 1 ? 'PCD' : '',
                            (int) ($familia['possui_gestantes'] ?? 0) === 1 ? 'Gestantes' : '',
                        ]));
                        ?>
                        <table class="dti-table dti-family-table">
                            <caption>Família <?= h($index + 1) ?> - <?= h($familia['responsavel_nome']) ?></caption>
                            <tbody>
                                <tr>
                                    <th>Responsável</th>
                                    <td><?= h($familia['responsavel_nome']) ?></td>
                                    <th>CPF</th>
                                    <td><?= h($familia['responsavel_cpf']) ?></td>
                                </tr>
                                <tr>
                                    <th>RG / Órgão</th>
                                    <td><?= h($valueOrDash($familia['responsavel_rg'] ?? '')) ?> / <?= h($valueOrDash($familia['responsavel_orgao_expedidor'] ?? '')) ?></td>
                                    <th>Sexo / Nascimento</th>
                                    <td><?= h($sexLabel($familia['responsavel_sexo'] ?? null)) ?> / <?= h($formatDate($familia['data_nascimento'] ?? '')) ?></td>
                                </tr>
                                <tr>
                                    <th>Telefone</th>
                                    <td><?= h($valueOrDash($familia['telefone'] ?? '')) ?></td>
                                    <th>E-mail</th>
                                    <td><?= h($valueOrDash($familia['email'] ?? '')) ?></td>
                                </tr>
                                <tr>
                                    <th>Integrantes</th>
                                    <td><?= h((int) ($familia['quantidade_integrantes'] ?? 0)) ?></td>
                                    <th>Renda familiar</th>
                                    <td><?= h(familia_renda_label($familia['renda_familiar'] ?? null)) ?></td>
                                </tr>
                                <tr>
                                    <th>Situação</th>
                                    <td><?= h(familia_situacao_label($familia['situacao_familia'] ?? null)) ?></td>
                                    <th>Vulnerabilidades</th>
                                    <td><?= h($vulnerabilidades !== [] ? implode(', ', $vulnerabilidades) : '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Benefício social</th>
                                    <td><?= h($yesNo($familia['recebe_beneficio_social'] ?? 0)) ?> - <?= h($valueOrDash($familia['beneficio_social_nome'] ?? '')) ?></td>
                                    <th>Cadastro concluído</th>
                                    <td><?= h($yesNo($familia['cadastro_concluido'] ?? 0)) ?></td>
                                </tr>
                                <tr>
                                    <th>Perdas de bens móveis</th>
                                    <td colspan="3"><?= h($valueOrDash($familia['perdas_bens_moveis'] ?? '')) ?></td>
                                </tr>
                                <tr>
                                    <th>Observações finais</th>
                                    <td colspan="3"><?= h($valueOrDash($familia['conclusao_observacoes'] ?? '')) ?></td>
                                </tr>
                                <tr>
                                    <th>Representante</th>
                                    <td><?= h($valueOrDash($familia['representante_nome'] ?? '')) ?></td>
                                    <th>CPF / RG</th>
                                    <td><?= h($valueOrDash($familia['representante_cpf'] ?? '')) ?> / <?= h($valueOrDash($familia['representante_rg'] ?? '')) ?></td>
                                </tr>
                                <tr>
                                    <th>Órgão expedidor</th>
                                    <td><?= h($valueOrDash($familia['representante_orgao_expedidor'] ?? '')) ?></td>
                                    <th>Sexo / Nascimento</th>
                                    <td><?= h($sexLabel($familia['representante_sexo'] ?? null)) ?> / <?= h($formatDate($familia['representante_data_nascimento'] ?? '')) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <footer class="dti-page-footer">
                <span><?= h($documentCode) ?></span>
                <span>Folha <?= h($pageNumber++) ?> de <?= h($totalPages) ?></span>
            </footer>
        </article>
        <?php endforeach; ?>

        <?php foreach ($photoBlocks as $photoBlockIndex => $photoBlock): ?>
        <?php
        $isLastPhotoPage = $photoBlockIndex === count($photoBlocks) - 1;
        $fotosPagina = $photoBlock['photos'];
        ?>
        <article class="dti-page dti-photo-page">
            <header class="dti-page-heading">
                <strong><?= $isLastPhotoPage ? 'Relatório fotográfico e assinatura' : 'Relatório fotográfico' ?></strong>
                <span><?= h($residencia['protocolo']) ?></span>
            </header>

            <section class="dti-section">
                <h3>4.<?= h($photoBlock['section']) ?> <?= h($photoBlock['title']) ?><?= (int) $photoBlock['total_parts'] > 1 ? ' - parte ' . h($photoBlock['part']) : '' ?></h3>
                <?php if ($fotosPagina === []): ?>
                    <div class="dti-empty"><?= h($photoBlock['empty']) ?></div>
                <?php else: ?>
                    <div class="dti-photo-grid">
                        <?php foreach ($fotosPagina as $foto): ?>
                            <?php $fotoUrl = url('/cadastros/residencias/' . $residencia['id'] . '/documentos/' . $foto['id']); ?>
                            <figure>
                                <img src="<?= h($fotoUrl) ?>" alt="<?= h($foto['nome_original']) ?>">
                                <figcaption>
                                    <strong><?= h($foto['nome_original']) ?></strong>
                                    <span><?= h($foto['responsavel_nome'] ?? 'Residência') ?> - <?= h($formatDateTime($foto['criado_em'] ?? '')) ?></span>
                                </figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($isLastPhotoPage): ?>
            <section class="dti-section dti-signature-section">
                <h3>5. Assinatura digital</h3>
                <?php if ($signature === null): ?>
                    <div class="dti-signature-pending">
                        Documento ainda não assinado. Use a ação "Assinar documento" na prévia antes da impressão oficial.
                    </div>
                <?php else: ?>
                    <div class="dti-signature-card">
                        <img class="dti-signature-logo" src="<?= h(asset('images/logo-cedec.png')) ?>" alt="CEDEC-PA">
                        <div>
                            <span>Assinado digitalmente no sistema Cadastro Emergencial</span>
                            <strong><?= h($valueOrDash($signatureSigners[0]['nome'] ?? ($signature['nome'] ?? ''))) ?></strong>
                            <p>Assinante principal</p>
                            <p>
                                <?= h($valueOrDash($signatureSigners[0]['graduacao'] ?? ($signature['graduacao'] ?? ''))) ?>
                                <?php if (!empty($signatureSigners[0]['nome_guerra'] ?? ($signature['nome_guerra'] ?? ''))): ?>
                                    - <?= h($signatureSigners[0]['nome_guerra'] ?? $signature['nome_guerra']) ?>
                                <?php endif; ?>
                            </p>
                            <p>CPF: <?= h($valueOrDash($signatureSigners[0]['cpf'] ?? ($signature['cpf'] ?? ''))) ?> | Data/hora: <?= h($signedAtText) ?></p>
                            <?php if (!empty($signatureSigners[0]['matricula_funcional'] ?? ($signature['matricula_funcional'] ?? ''))): ?>
                                <p>MF: <?= h($signatureSigners[0]['matricula_funcional'] ?? $signature['matricula_funcional']) ?></p>
                            <?php endif; ?>
                            <p>Hash: <?= h($signatureHash !== '' ? substr($signatureHash, 0, 16) . '...' . substr($signatureHash, -12) : '-') ?></p>
                        </div>
                    </div>
                    <?php if (count($signatureSigners) > 1): ?>
                        <div class="dti-cosigner-list">
                            <span>Coassinantes</span>
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
                            <span>Status dos coassinantes</span>
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
            <?php endif; ?>

            <footer class="dti-page-footer">
                <span><?= h($documentCode) ?></span>
                <span>Folha <?= h($pageNumber++) ?> de <?= h($totalPages) ?></span>
            </footer>
        </article>
        <?php endforeach; ?>
    </div>
</section>
