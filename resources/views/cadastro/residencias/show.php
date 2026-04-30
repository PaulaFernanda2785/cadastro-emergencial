<?php
$fotoPrincipal = null;

foreach ($documentos as $documento) {
    $isResidencePhoto = (string) ($documento['tipo_documento'] ?? '') === 'foto_georreferenciada'
        && (int) ($documento['residencia_id'] ?? 0) === (int) $residencia['id']
        && str_starts_with((string) ($documento['mime_type'] ?? ''), 'image/');

    if ($isResidencePhoto) {
        $fotoPrincipal = $documento;
        break;
    }
}

$fotoPrincipalUrl = $fotoPrincipal !== null
    ? url('/cadastros/residencias/' . $residencia['id'] . '/documentos/' . $fotoPrincipal['id'])
    : null;
$familiasCadastradas = count($familias);
$familiasPrevistas = (int) ($residencia['quantidade_familias'] ?? 0);
$podeCadastrarFamilia = $familiasCadastradas < max(1, $familiasPrevistas);
?>

<section class="dashboard-header">
    <div>
        <span class="eyebrow">Residencia</span>
        <h1><?= h($residencia['protocolo']) ?></h1>
        <p><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?> - <?= h($residencia['bairro_comunidade']) ?></p>
    </div>
    <?php if (($residencia['acao_status'] ?? null) === 'aberta'): ?>
        <div class="header-actions">
            <a class="secondary-button residence-action-button" href="<?= h(url('/acao/' . $residencia['token_publico'] . '/residencias/novo')) ?>">Nova residencia</a>
            <?php if ($podeCadastrarFamilia): ?>
                <a class="primary-link-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/novo')) ?>">Nova familia</a>
            <?php else: ?>
                <span class="limit-reached-pill">Limite de familias atingido</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<section class="residence-summary-grid">
    <article class="residence-hero-panel">
        <div class="residence-card-heading">
            <span>Endereco</span>
            <strong><?= h($residencia['bairro_comunidade']) ?></strong>
        </div>
        <p><?= h($residencia['endereco']) ?></p>
        <?php if (!empty($residencia['complemento'])): ?>
            <small><?= h($residencia['complemento']) ?></small>
        <?php endif; ?>
        <dl class="residence-meta-list">
            <div>
                <dt>Municipio</dt>
                <dd><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?></dd>
            </div>
            <div>
                <dt>Cadastrador</dt>
                <dd><?= h($residencia['cadastrador_nome']) ?></dd>
            </div>
            <div>
                <dt>Familias</dt>
                <dd><?= h($familiasCadastradas) ?> de <?= h($familiasPrevistas) ?></dd>
            </div>
        </dl>
    </article>

    <article class="residence-photo-panel">
        <div class="residence-card-heading">
            <span>Foto georreferenciada</span>
            <strong><?= $fotoPrincipal ? 'Disponivel' : 'Nao anexada' ?></strong>
        </div>
        <?php if ($fotoPrincipalUrl !== null): ?>
            <button class="residence-photo-preview" type="button" data-residence-image-open data-image-src="<?= h($fotoPrincipalUrl) ?>" data-image-title="<?= h($fotoPrincipal['nome_original']) ?>">
                <img src="<?= h($fotoPrincipalUrl) ?>" alt="Foto georreferenciada da residencia">
            </button>
            <button class="primary-button residence-photo-button" type="button" data-residence-image-open data-image-src="<?= h($fotoPrincipalUrl) ?>" data-image-title="<?= h($fotoPrincipal['nome_original']) ?>">Visualizar imagem</button>
        <?php else: ?>
            <div class="residence-photo-empty">Sem imagem registrada para esta residencia.</div>
        <?php endif; ?>
    </article>
</section>

<section class="detail-grid residence-detail-grid">
    <article class="detail-panel">
        <h2>Acao emergencial</h2>
        <p><?= h($residencia['localidade']) ?> - <?= h($residencia['tipo_evento']) ?></p>
        <span class="status-pill status-<?= h((string) $residencia['acao_status']) ?>"><?= h(ucfirst((string) $residencia['acao_status'])) ?></span>
    </article>
    <article class="detail-panel">
        <h2>Geolocalizacao</h2>
        <p>Latitude: <?= h($residencia['latitude'] ?: '-') ?></p>
        <p>Longitude: <?= h($residencia['longitude'] ?: '-') ?></p>
    </article>
    <article class="detail-panel">
        <h2>Registro</h2>
        <p>Cadastro realizado por <?= h($residencia['cadastrador_nome']) ?>.</p>
        <p><?= h($familiasCadastradas) ?> familia(s) vinculada(s).</p>
    </article>
</section>

<section class="table-panel attachments-panel residence-record-panel">
    <div class="table-heading">
        <h2>Anexos privados</h2>
        <span><?= h(count($documentos)) ?> arquivo(s)</span>
    </div>
    <table class="data-table residence-record-table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Arquivo</th>
                <th>Vinculo</th>
                <th>Tamanho</th>
                <th>Enviado em</th>
                <th class="actions-column">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documentos as $documento): ?>
                <?php $isImage = str_starts_with((string) $documento['mime_type'], 'image/'); ?>
                <tr>
                    <td data-label="Tipo"><?= h($documento['tipo_documento']) ?></td>
                    <td data-label="Arquivo"><?= h($documento['nome_original']) ?></td>
                    <td data-label="Vinculo"><?= $documento['familia_id'] ? h($documento['responsavel_nome']) : 'Residencia' ?></td>
                    <td data-label="Tamanho"><?= h(number_format(((int) $documento['tamanho_bytes']) / 1024, 1, ',', '.')) ?> KB</td>
                    <td data-label="Enviado em"><?= h(date('d/m/Y H:i', strtotime((string) $documento['criado_em']))) ?></td>
                    <td class="actions-column" data-label="Acoes">
                        <?php if ($isImage): ?>
                            <button class="table-action-link" type="button" data-residence-image-open data-image-src="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/documentos/' . $documento['id'])) ?>" data-image-title="<?= h($documento['nome_original']) ?>">Visualizar</button>
                        <?php else: ?>
                            <span class="muted-action">Indisponivel</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($documentos === []): ?>
                <tr>
                    <td colspan="6" class="empty-state">Nenhum anexo registrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<section class="table-panel residence-record-panel">
    <?php $canRegisterDelivery = in_array((string) (current_user()['perfil'] ?? ''), ['gestor', 'administrador'], true); ?>
    <div class="table-heading">
        <h2>Familias vinculadas</h2>
        <span><?= h(count($familias)) ?> cadastrada(s) de <?= h($residencia['quantidade_familias']) ?> informada(s)</span>
    </div>
    <table class="data-table residence-record-table">
        <thead>
            <tr>
                <th>Responsavel</th>
                <th>CPF</th>
                <th>Telefone</th>
                <th>Integrantes</th>
                <th>Vulnerabilidades</th>
                <th>Entregas</th>
                <th class="actions-column">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($familias as $familia): ?>
                <tr>
                    <td data-label="Responsavel"><?= h($familia['responsavel_nome']) ?></td>
                    <td data-label="CPF"><?= h($familia['responsavel_cpf']) ?></td>
                    <td data-label="Telefone"><?= h($familia['telefone'] ?: '-') ?></td>
                    <td data-label="Integrantes"><?= h($familia['quantidade_integrantes']) ?></td>
                    <td data-label="Vulnerabilidades">
                        <?= (int) $familia['possui_criancas'] === 1 ? 'Criancas ' : '' ?>
                        <?= (int) $familia['possui_idosos'] === 1 ? 'Idosos ' : '' ?>
                        <?= (int) $familia['possui_pcd'] === 1 ? 'PCD' : '' ?>
                        <?= (int) ($familia['possui_gestantes'] ?? 0) === 1 ? 'Gestantes' : '' ?>
                    </td>
                    <td data-label="Entregas"><?= h($familia['entregas_registradas'] ?? 0) ?></td>
                    <td class="actions-column" data-label="Acoes">
                        <div class="family-row-actions">
                            <a class="table-action-link" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'])) ?>">Ver detalhe</a>
                            <?php if (($residencia['acao_status'] ?? null) === 'aberta'): ?>
                                <a class="table-action-link" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'] . '/editar')) ?>">Editar</a>
                                <form method="post" action="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'] . '/excluir')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Excluir esta familia da listagem? O registro continuara preservado no banco.">
                                    <?= csrf_field() ?>
                                    <?= idempotency_field('cadastro.familia.delete.' . $familia['id']) ?>
                                    <button type="submit" class="danger-button table-danger-button" data-loading-text="Excluindo...">Excluir</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canRegisterDelivery): ?>
                                <a class="table-action-link" href="<?= h(url('/gestor/familias/' . $familia['id'] . '/entregas/novo')) ?>">Entrega</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($familias === []): ?>
                <tr>
                    <td colspan="7" class="empty-state">Nenhuma familia cadastrada para esta residencia.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
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
