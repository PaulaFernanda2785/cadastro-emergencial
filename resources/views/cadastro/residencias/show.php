<section class="dashboard-header">
    <div>
        <span class="eyebrow">Residencia</span>
        <h1><?= h($residencia['protocolo']) ?></h1>
        <p><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?> - <?= h($residencia['bairro_comunidade']) ?></p>
    </div>
    <?php if (($residencia['acao_status'] ?? null) === 'aberta'): ?>
        <div class="header-actions">
            <a class="secondary-button" href="<?= h(url('/acao/' . $residencia['token_publico'] . '/residencias/novo')) ?>">Nova residencia</a>
            <a class="primary-link-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/novo')) ?>">Nova familia</a>
        </div>
    <?php endif; ?>
</section>

<section class="detail-grid">
    <article class="detail-panel">
        <h2>Endereco</h2>
        <p><?= h($residencia['endereco']) ?></p>
        <?php if (!empty($residencia['complemento'])): ?>
            <p><?= h($residencia['complemento']) ?></p>
        <?php endif; ?>
    </article>
    <article class="detail-panel">
        <h2>Acao</h2>
        <p><?= h($residencia['localidade']) ?> - <?= h($residencia['tipo_evento']) ?></p>
        <p>Status: <?= h(ucfirst((string) $residencia['acao_status'])) ?></p>
    </article>
    <article class="detail-panel">
        <h2>Geolocalizacao</h2>
        <p>Latitude: <?= h($residencia['latitude'] ?: '-') ?></p>
        <p>Longitude: <?= h($residencia['longitude'] ?: '-') ?></p>
    </article>
</section>

<section class="table-panel attachments-panel">
    <div class="table-heading">
        <h2>Anexos privados</h2>
        <span><?= h(count($documentos)) ?> arquivo(s)</span>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Arquivo</th>
                <th>Vinculo</th>
                <th>Tamanho</th>
                <th>Enviado em</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documentos as $documento): ?>
                <tr>
                    <td><?= h($documento['tipo_documento']) ?></td>
                    <td><?= h($documento['nome_original']) ?></td>
                    <td><?= $documento['familia_id'] ? h($documento['responsavel_nome']) : 'Residencia' ?></td>
                    <td><?= h(number_format(((int) $documento['tamanho_bytes']) / 1024, 1, ',', '.')) ?> KB</td>
                    <td><?= h(date('d/m/Y H:i', strtotime((string) $documento['criado_em']))) ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if ($documentos === []): ?>
                <tr>
                    <td colspan="5" class="empty-state">Nenhum anexo registrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<section class="table-panel">
    <?php $canRegisterDelivery = in_array((string) (current_user()['perfil'] ?? ''), ['gestor', 'administrador'], true); ?>
    <div class="table-heading">
        <h2>Familias vinculadas</h2>
        <span><?= h(count($familias)) ?> cadastrada(s) de <?= h($residencia['quantidade_familias']) ?> informada(s)</span>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Responsavel</th>
                <th>CPF</th>
                <th>Telefone</th>
                <th>Integrantes</th>
                <th>Vulnerabilidades</th>
                <th>Entregas</th>
                <?php if ($canRegisterDelivery): ?>
                    <th class="actions-column">Acoes</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($familias as $familia): ?>
                <tr>
                    <td><?= h($familia['responsavel_nome']) ?></td>
                    <td><?= h($familia['responsavel_cpf']) ?></td>
                    <td><?= h($familia['telefone'] ?: '-') ?></td>
                    <td><?= h($familia['quantidade_integrantes']) ?></td>
                    <td>
                        <?= (int) $familia['possui_criancas'] === 1 ? 'Criancas ' : '' ?>
                        <?= (int) $familia['possui_idosos'] === 1 ? 'Idosos ' : '' ?>
                        <?= (int) $familia['possui_pcd'] === 1 ? 'PCD' : '' ?>
                    </td>
                    <td><?= h($familia['entregas_registradas'] ?? 0) ?></td>
                    <?php if ($canRegisterDelivery): ?>
                        <td class="actions-column">
                            <a href="<?= h(url('/gestor/familias/' . $familia['id'] . '/entregas/novo')) ?>">Registrar entrega</a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>

            <?php if ($familias === []): ?>
                <tr>
                    <td colspan="<?= $canRegisterDelivery ? '7' : '6' ?>" class="empty-state">Nenhuma familia cadastrada para esta residencia.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
