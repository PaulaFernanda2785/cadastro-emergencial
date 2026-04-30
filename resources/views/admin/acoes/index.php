<section class="dashboard-header">
    <div>
        <span class="eyebrow">Administracao</span>
        <h1>Acoes emergenciais</h1>
        <p>Cadastre a acao que sera acessada por QR Code pelas equipes de campo.</p>
    </div>
    <a class="primary-link-button" href="<?= h(url('/admin/acoes/novo')) ?>">Nova acao</a>
</section>

<section class="table-panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Municipio</th>
                <th>Localidade</th>
                <th>Evento</th>
                <th>Data</th>
                <th>Status</th>
                <th>Link publico</th>
                <th class="actions-column">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($acoes as $acao): ?>
                <tr>
                    <td><?= h($acao['municipio_nome']) ?> / <?= h($acao['uf']) ?></td>
                    <td><?= h($acao['localidade']) ?></td>
                    <td><?= h($acao['tipo_evento']) ?></td>
                    <td><?= h(date('d/m/Y', strtotime((string) $acao['data_evento']))) ?></td>
                    <td><span class="status status-<?= h($acao['status']) ?>"><?= h(ucfirst($acao['status'])) ?></span></td>
                    <td><a href="<?= h(url('/acao/' . $acao['token_publico'])) ?>" target="_blank" rel="noopener">Abrir</a></td>
                    <td class="actions-column"><a href="<?= h(url('/admin/acoes/' . $acao['id'] . '/editar')) ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>

            <?php if ($acoes === []): ?>
                <tr>
                    <td colspan="7" class="empty-state">Nenhuma acao emergencial cadastrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
