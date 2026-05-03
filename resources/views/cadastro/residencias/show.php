<?php
$fotoPrincipal = null;
$fotosExtras = [];

foreach ($documentos as $documento) {
    $isResidencePhoto = (string) ($documento['tipo_documento'] ?? '') === 'foto_georreferenciada'
        && (int) ($documento['residencia_id'] ?? 0) === (int) $residencia['id']
        && str_starts_with((string) ($documento['mime_type'] ?? ''), 'image/');
    $isExtraResidencePhoto = (string) ($documento['tipo_documento'] ?? '') === 'foto_residencia_extra'
        && (int) ($documento['residencia_id'] ?? 0) === (int) $residencia['id']
        && str_starts_with((string) ($documento['mime_type'] ?? ''), 'image/');

    if ($isResidencePhoto && $fotoPrincipal === null) {
        $fotoPrincipal = $documento;
    }

    if ($isExtraResidencePhoto) {
        $fotosExtras[] = $documento;
    }
}

$fotoPrincipalUrl = $fotoPrincipal !== null
    ? url('/cadastros/residencias/' . $residencia['id'] . '/documentos/' . $fotoPrincipal['id'])
    : null;
$familiasCadastradas = count($familias);
$familiasPrevistas = max(1, (int) ($residencia['quantidade_familias'] ?? 1));
$familiasPercentual = min(100, (int) round(($familiasCadastradas / $familiasPrevistas) * 100));
$podeCadastrarFamilia = $familiasCadastradas < $familiasPrevistas;
$condicao = (string) ($residencia['condicao_residencia'] ?? '');
$condicaoClass = $condicao !== '' ? preg_replace('/[^a-z0-9_-]+/i', '-', $condicao) : 'sem-condicao';
$dataCadastro = strtotime((string) ($residencia['data_cadastro'] ?? ''));
$canRegisterDelivery = in_array((string) (current_user()['perfil'] ?? ''), ['gestor', 'administrador'], true);
?>

<section class="records-page residence-open-page">
    <header class="action-form-header records-header">
        <div>
            <span class="eyebrow">Residência</span>
            <h1><?= h($residencia['protocolo']) ?></h1>
            <p><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?> - <?= h($residencia['bairro_comunidade']) ?></p>
        </div>
        <div class="header-actions">
            <a class="secondary-button residence-action-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/dti')) ?>">DTI</a>
            <?php if (($residencia['acao_status'] ?? null) === 'aberta'): ?>
                <a class="secondary-button residence-action-button" href="<?= h(url('/acao/' . $residencia['token_publico'] . '/residencias/novo')) ?>">Nova residência</a>
                <a class="secondary-button residence-action-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/editar')) ?>">Editar residência</a>
                <?php if ($podeCadastrarFamilia): ?>
                    <a class="primary-link-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/novo')) ?>">Nova família</a>
                <?php else: ?>
                    <span class="limit-reached-pill">Limite de famílias atingido</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </header>

    <section class="records-summary-grid" aria-label="Resumo da residência">
        <article class="records-summary-card">
            <span>Famílias</span>
            <strong><?= h($familiasCadastradas) ?> / <?= h($familiasPrevistas) ?></strong>
            <small>Famílias cadastradas sobre o limite informado.</small>
        </article>
        <article class="records-summary-card">
            <span>Condição</span>
            <strong><?= h(residencia_condicao_label($residencia['condicao_residencia'] ?? null)) ?></strong>
            <small>Situação declarada para o imóvel.</small>
        </article>
        <article class="records-summary-card">
            <span>Imóvel</span>
            <strong><?= h(residencia_imovel_label($residencia['imovel'] ?? null)) ?></strong>
            <small>Tipo de ocupação informada.</small>
        </article>
        <article class="records-summary-card">
            <span>Cadastro</span>
            <strong><?= $dataCadastro !== false ? h(date('d/m/Y', $dataCadastro)) : '-' ?></strong>
            <small><?= $dataCadastro !== false ? h(date('H:i', $dataCadastro)) : 'Sem data registrada' ?></small>
        </article>
    </section>

    <article class="record-card residence-open-card">
        <div class="record-card-main">
            <div class="record-card-title">
                <span class="record-protocol"><?= h($residencia['protocolo']) ?></span>
                <h2><?= h($residencia['bairro_comunidade']) ?></h2>
                <p><?= h($residencia['endereco']) ?></p>
                <?php if (!empty($residencia['complemento'])): ?>
                    <p><?= h($residencia['complemento']) ?></p>
                <?php endif; ?>
            </div>

            <dl class="record-card-meta">
                <div>
                    <dt>Ação</dt>
                    <dd><?= h($residencia['localidade']) ?> - <?= h($residencia['tipo_evento']) ?></dd>
                </div>
                <div>
                    <dt>Município</dt>
                    <dd><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?></dd>
                </div>
                <div>
                    <dt>Condição</dt>
                    <dd><span class="record-condition record-condition-<?= h($condicaoClass) ?>"><?= h(residencia_condicao_label($residencia['condicao_residencia'] ?? null)) ?></span></dd>
                </div>
                <div>
                    <dt>Cadastrador</dt>
                    <dd><?= h($residencia['cadastrador_nome']) ?></dd>
                </div>
            </dl>
        </div>

        <aside class="record-card-side" aria-label="Indicadores da residência">
            <div class="record-family-meter">
                <div>
                    <span>Famílias</span>
                    <strong><?= h($familiasCadastradas) ?> / <?= h($familiasPrevistas) ?></strong>
                </div>
                <div class="record-progress" aria-hidden="true">
                    <span style="width: <?= h((string) $familiasPercentual) ?>%"></span>
                </div>
            </div>

            <div class="record-card-date">
                <span>Status da ação</span>
                <strong><?= h(ucfirst((string) $residencia['acao_status'])) ?></strong>
            </div>

            <div class="record-card-date">
                <span>Geolocalização</span>
                <strong><?= h($residencia['latitude'] ?: '-') ?> / <?= h($residencia['longitude'] ?: '-') ?></strong>
            </div>
        </aside>
    </article>

    <section class="residence-summary-grid residence-media-grid">
        <article class="residence-photo-panel residence-main-photo-panel">
            <div class="residence-card-heading">
                <span>Foto georreferenciada</span>
                <strong><?= $fotoPrincipal ? 'Disponível' : 'Não anexada' ?></strong>
            </div>
            <?php if ($fotoPrincipalUrl !== null): ?>
                <button class="residence-photo-preview" type="button" data-residence-image-open data-image-src="<?= h($fotoPrincipalUrl) ?>" data-image-title="<?= h($fotoPrincipal['nome_original']) ?>">
                    <img src="<?= h($fotoPrincipalUrl) ?>" alt="Foto georreferenciada da residência">
                </button>
                <button class="primary-button residence-photo-button" type="button" data-residence-image-open data-image-src="<?= h($fotoPrincipalUrl) ?>" data-image-title="<?= h($fotoPrincipal['nome_original']) ?>">Visualizar imagem</button>
            <?php else: ?>
                <div class="residence-photo-empty">Sem imagem registrada para esta residência.</div>
            <?php endif; ?>
        </article>

        <article class="residence-photo-panel residence-extra-photo-panel">
            <div class="residence-card-heading">
                <span>Fotos adicionais</span>
                <strong><?= h(count($fotosExtras)) ?> de 3</strong>
            </div>
            <?php if ($fotosExtras !== []): ?>
                <div class="residence-extra-photo-grid">
                    <?php foreach ($fotosExtras as $fotoExtra): ?>
                        <?php $fotoExtraUrl = url('/cadastros/residencias/' . $residencia['id'] . '/documentos/' . $fotoExtra['id']); ?>
                        <button class="residence-photo-preview" type="button" data-residence-image-open data-image-src="<?= h($fotoExtraUrl) ?>" data-image-title="<?= h($fotoExtra['nome_original']) ?>">
                            <img src="<?= h($fotoExtraUrl) ?>" alt="Foto adicional da residência">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="residence-photo-empty">Nenhuma foto adicional registrada.</div>
            <?php endif; ?>
        </article>
    </section>

    <section class="table-panel residence-record-panel residence-family-panel">
        <div class="table-heading">
            <h2>Famílias vinculadas</h2>
            <span><?= h(count($familias)) ?> cadastrada(s) de <?= h($residencia['quantidade_familias']) ?> informada(s)</span>
        </div>
        <?php if ($familias === []): ?>
            <div class="empty-state">Nenhuma família cadastrada para esta residência.</div>
        <?php else: ?>
            <div class="residence-family-list">
                <?php foreach ($familias as $familia): ?>
                    <?php
                    $vulnerabilidades = array_values(array_filter([
                        (int) $familia['possui_criancas'] === 1 ? 'Crianças' : '',
                        (int) $familia['possui_idosos'] === 1 ? 'Idosos' : '',
                        (int) $familia['possui_pcd'] === 1 ? 'PCD' : '',
                        (int) ($familia['possui_gestantes'] ?? 0) === 1 ? 'Gestantes' : '',
                    ]));
                    $entregasRegistradas = (int) ($familia['entregas_registradas'] ?? 0);
                    ?>
                    <article class="residence-family-card <?= $entregasRegistradas > 0 ? 'has-delivery' : 'without-delivery' ?>">
                        <div class="residence-family-main">
                            <span class="eyebrow">Responsável familiar</span>
                            <strong><?= h($familia['responsavel_nome']) ?></strong>
                            <small><?= h($familia['responsavel_cpf']) ?></small>
                        </div>

                        <div class="residence-family-status">
                            <span><?= $entregasRegistradas > 0 ? 'Com entrega' : 'Sem entrega' ?></span>
                            <strong><?= h($entregasRegistradas) ?></strong>
                        </div>

                        <div class="residence-family-meta">
                            <span>
                                <small>Telefone</small>
                                <strong><?= h($familia['telefone'] ?: '-') ?></strong>
                            </span>
                            <span>
                                <small>Integrantes</small>
                                <strong><?= h((int) $familia['quantidade_integrantes']) ?></strong>
                            </span>
                            <span>
                                <small>Vulnerabilidades</small>
                                <strong><?= h($vulnerabilidades !== [] ? implode(', ', $vulnerabilidades) : '-') ?></strong>
                            </span>
                        </div>

                        <div class="residence-family-actions">
                            <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'])) ?>">Ver detalhe</a>
                            <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'] . '/comprovante')) ?>">Comprovante</a>
                            <?php if (($residencia['acao_status'] ?? null) === 'aberta'): ?>
                                <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'] . '/editar')) ?>">Editar</a>
                                <form method="post" action="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'] . '/excluir')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Excluir esta família da listagem? O registro continuará preservado no banco.">
                                    <?= csrf_field() ?>
                                    <?= idempotency_field('cadastro.familia.delete.' . $familia['id']) ?>
                                    <button type="submit" class="danger-button" data-loading-text="Excluindo...">Excluir</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canRegisterDelivery): ?>
                                <a class="primary-link-button" href="<?= h(url('/gestor/familias/' . $familia['id'] . '/entregas/novo')) ?>">Entrega</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<dialog class="residence-image-modal" data-residence-image-modal aria-labelledby="residence-image-modal-title">
    <form method="dialog" class="residence-image-modal-close-form">
        <button type="submit" class="residence-image-modal-close" aria-label="Fechar imagem">Fechar</button>
    </form>
    <div class="residence-image-modal-content">
        <h2 id="residence-image-modal-title" data-residence-image-title>Imagem</h2>
        <img alt="Imagem ampliada do cadastro" data-residence-image>
    </div>
</dialog>
