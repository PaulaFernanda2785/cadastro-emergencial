<?php
$vulnerabilidades = array_values(array_filter([
    (int) $familia['possui_criancas'] === 1 ? 'Crianças' : '',
    (int) $familia['possui_idosos'] === 1 ? 'Idosos' : '',
    (int) $familia['possui_pcd'] === 1 ? 'PCD' : '',
    (int) ($familia['possui_gestantes'] ?? 0) === 1 ? 'Gestantes' : '',
]));
$dataNascimento = !empty($familia['data_nascimento']) ? date('d/m/Y', strtotime((string) $familia['data_nascimento'])) : '-';
$representanteNascimento = !empty($familia['representante_data_nascimento']) ? date('d/m/Y', strtotime((string) $familia['representante_data_nascimento'])) : '-';
$camposPendentes = familia_campos_pendentes($familia);
$documentos = $documentos ?? [];
$familiaId = (int) ($familia['id'] ?? 0);
?>

<section class="family-detail-page">
    <header class="dashboard-header family-detail-header">
        <div>
            <span class="eyebrow">Família vinculada</span>
            <h1><?= h($familia['responsavel_nome']) ?></h1>
            <p>Residência <?= h($residencia['protocolo']) ?> - <?= h($residencia['bairro_comunidade']) ?></p>
        </div>
        <div class="header-actions">
            <a class="secondary-button residence-action-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'])) ?>">Voltar para residência</a>
            <a class="secondary-button residence-action-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'] . '/comprovante')) ?>">Comprovante</a>
            <?php if (($residencia['acao_status'] ?? null) === 'aberta'): ?>
                <a class="primary-link-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'] . '/editar')) ?>">Editar família</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="family-detail-summary">
        <article class="family-detail-profile">
            <span class="eyebrow">Responsável familiar</span>
            <h2><?= h($familia['responsavel_nome']) ?></h2>
            <p><?= h($familia['responsavel_cpf'] ?: 'CPF pendente') ?><?= !empty($familia['responsavel_rg']) ? ' - RG ' . h($familia['responsavel_rg']) : '' ?></p>
            <div class="family-detail-tags">
                <span><?= h((int) $familia['quantidade_integrantes']) ?> integrante(s)</span>
                <span><?= h($vulnerabilidades !== [] ? implode(', ', $vulnerabilidades) : 'Sem vulnerabilidade marcada') ?></span>
            </div>
        </article>

        <article class="family-detail-status">
            <span>Status da ação</span>
            <strong><?= h(ucfirst((string) $residencia['acao_status'])) ?></strong>
            <em class="status-pill status-<?= h((string) $residencia['acao_status']) ?>"><?= h($residencia['localidade']) ?> - <?= h($residencia['tipo_evento']) ?></em>
        </article>
    </section>

    <section class="family-pending-fields family-detail-pending">
        <span>Campos pendentes</span>
        <strong><?= h(familia_campos_pendentes_resumo($familia, 8)) ?></strong>
    </section>

    <section class="family-detail-card family-detail-documents">
        <div class="family-detail-card-heading">
            <span>Arquivos</span>
            <strong>Documentos anexados</strong>
        </div>

        <?php if ($documentos === []): ?>
            <p class="family-detail-documents-empty">Nenhum documento anexado a esta família.</p>
        <?php else: ?>
            <div class="family-detail-doc-grid">
                <?php foreach ($documentos as $documento): ?>
                    <?php
                    $documentoUrl = url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familiaId . '/documentos/' . $documento['id']);
                    $isImage = str_starts_with((string) ($documento['mime_type'] ?? ''), 'image/');
                    $documentoPreviewUrl = $isImage ? $documentoUrl . '?thumb=1' : $documentoUrl;
                    ?>
                    <article class="family-detail-doc-item">
                        <button class="family-detail-doc-preview" type="button" data-family-doc-open data-doc-src="<?= h($documentoUrl) ?>" data-doc-title="<?= h($documento['nome_original']) ?>" data-doc-kind="<?= $isImage ? 'image' : 'document' ?>">
                            <?php if ($isImage): ?>
                                <img src="<?= h($documentoPreviewUrl) ?>" alt="Documento <?= h($documento['nome_original']) ?>" width="420" height="315" loading="lazy" decoding="async" fetchpriority="low">
                            <?php else: ?>
                                <span class="family-doc-preview-icon"><?= h(strtoupper((string) ($documento['extensao'] ?? 'PDF'))) ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="family-doc-info">
                            <span><?= h($documento['nome_original']) ?></span>
                            <small><?= h(number_format(((int) $documento['tamanho_bytes']) / 1024, 1, ',', '.')) ?> KB - <?= h(date('d/m/Y H:i', strtotime((string) $documento['criado_em']))) ?></small>
                        </div>
                        <button class="family-doc-view-link" type="button" data-family-doc-open data-doc-src="<?= h($documentoUrl) ?>" data-doc-title="<?= h($documento['nome_original']) ?>" data-doc-kind="<?= $isImage ? 'image' : 'document' ?>">Visualizar</button>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="family-detail-grid">
        <article class="family-detail-card">
            <div class="family-detail-card-heading">
                <span>Dados pessoais</span>
                <strong>Responsável</strong>
            </div>
            <dl class="family-detail-list">
                <div><dt>CPF</dt><dd><?= h($familia['responsavel_cpf'] ?: '-') ?></dd></div>
                <div><dt>RG</dt><dd><?= h($familia['responsavel_rg'] ?: '-') ?></dd></div>
                <div><dt>Órgão exp.</dt><dd><?= h($familia['responsavel_orgao_expedidor'] ?: '-') ?></dd></div>
                <div><dt>Sexo</dt><dd><?= h($familia['responsavel_sexo'] ?: '-') ?></dd></div>
                <div><dt>Nascimento</dt><dd><?= h($dataNascimento) ?></dd></div>
            </dl>
        </article>

        <article class="family-detail-card">
            <div class="family-detail-card-heading">
                <span>Contato</span>
                <strong>Canais informados</strong>
            </div>
            <dl class="family-detail-list">
                <div><dt>Telefone</dt><dd><?= h($familia['telefone'] ?: '-') ?></dd></div>
                <div><dt>E-mail</dt><dd><?= h($familia['email'] ?: '-') ?></dd></div>
            </dl>
        </article>

        <article class="family-detail-card">
            <div class="family-detail-card-heading">
                <span>Composição</span>
                <strong>Família</strong>
            </div>
            <dl class="family-detail-list">
                <div><dt>Integrantes</dt><dd><?= h((int) $familia['quantidade_integrantes']) ?></dd></div>
                <div><dt>Vulnerabilidades</dt><dd><?= h($vulnerabilidades !== [] ? implode(', ', $vulnerabilidades) : '-') ?></dd></div>
                <div><dt>Renda</dt><dd><?= h(familia_renda_label($familia['renda_familiar'] ?? null)) ?></dd></div>
                <div><dt>Situação</dt><dd><?= h(familia_situacao_label($familia['situacao_familia'] ?? null)) ?></dd></div>
            </dl>
        </article>

        <article class="family-detail-card">
            <div class="family-detail-card-heading">
                <span>Representante</span>
                <strong>Preenchimento</strong>
            </div>
            <dl class="family-detail-list">
                <div><dt>Nome</dt><dd><?= h($familia['representante_nome'] ?: '-') ?></dd></div>
                <div><dt>CPF</dt><dd><?= h($familia['representante_cpf'] ?: '-') ?></dd></div>
                <div><dt>RG</dt><dd><?= h($familia['representante_rg'] ?: '-') ?></dd></div>
                <div><dt>Órgão exp.</dt><dd><?= h($familia['representante_orgao_expedidor'] ?: '-') ?></dd></div>
                <div><dt>Nascimento</dt><dd><?= h($representanteNascimento) ?></dd></div>
                <div><dt>Telefone</dt><dd><?= h($familia['representante_telefone'] ?: '-') ?></dd></div>
                <div><dt>E-mail</dt><dd><?= h($familia['representante_email'] ?: '-') ?></dd></div>
            </dl>
        </article>

        <article class="family-detail-card family-detail-card-wide">
            <div class="family-detail-card-heading">
                <span>Residência</span>
                <strong><?= h($residencia['protocolo']) ?></strong>
            </div>
            <dl class="family-detail-list family-detail-list-wide">
                <div><dt>Endereço</dt><dd><?= h($residencia['endereco']) ?><?= !empty($residencia['complemento']) ? ' - ' . h($residencia['complemento']) : '' ?></dd></div>
                <div><dt>Bairro</dt><dd><?= h($residencia['bairro_comunidade']) ?></dd></div>
                <div><dt>Município</dt><dd><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?></dd></div>
                <div><dt>Imóvel</dt><dd><?= h(residencia_imovel_label($residencia['imovel'] ?? null)) ?></dd></div>
                <div><dt>Condição</dt><dd><?= h(residencia_condicao_label($residencia['condicao_residencia'] ?? null)) ?></dd></div>
            </dl>
        </article>
    </section>

    <dialog class="family-doc-modal" data-family-doc-modal aria-labelledby="family-doc-modal-title">
        <form method="dialog" class="family-doc-modal-close-form">
            <button type="submit" class="family-doc-modal-close" aria-label="Fechar documento">Fechar</button>
        </form>
        <div class="family-doc-modal-content">
            <h2 id="family-doc-modal-title" data-family-doc-modal-title>Documento</h2>
            <img alt="Documento ampliado" data-family-doc-modal-image hidden>
            <iframe title="Documento anexado" data-family-doc-modal-frame hidden></iframe>
        </div>
    </dialog>
</section>
