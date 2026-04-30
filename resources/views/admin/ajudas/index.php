<section class="dashboard-header">
    <div>
        <span class="eyebrow">Administracao</span>
        <h1>Tipos de ajuda humanitaria</h1>
        <p>Materiais que poderao ser vinculados as entregas e a prestacao de contas.</p>
    </div>
    <a class="primary-link-button" href="<?= h(url('/admin/ajudas/novo')) ?>">Novo tipo</a>
</section>

<section class="table-panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Unidade</th>
                <th>Status</th>
                <th class="actions-column">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tipos as $tipo): ?>
                <tr>
                    <td><?= h($tipo['nome']) ?></td>
                    <td><?= h($tipo['unidade_medida']) ?></td>
                    <td><span class="status status-<?= (int) $tipo['ativo'] === 1 ? 'open' : 'closed' ?>"><?= (int) $tipo['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
                    <td class="actions-column"><a href="<?= h(url('/admin/ajudas/' . $tipo['id'] . '/editar')) ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>

            <?php if ($tipos === []): ?>
                <tr>
                    <td colspan="4" class="empty-state">Nenhum tipo de ajuda cadastrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
