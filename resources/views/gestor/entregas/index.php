<section class="dashboard-header">
    <div>
        <span class="eyebrow">Gestao operacional</span>
        <h1>Entregas de ajuda humanitaria</h1>
        <p>Historico de entregas registradas para familias cadastradas.</p>
    </div>
</section>

<section class="table-panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Comprovante</th>
                <th>Familia</th>
                <th>Ajuda</th>
                <th>Quantidade</th>
                <th>Residencia</th>
                <th>Entrega</th>
                <th class="actions-column">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entregas as $entrega): ?>
                <tr>
                    <td><?= h($entrega['comprovante_codigo']) ?></td>
                    <td>
                        <?= h($entrega['responsavel_nome']) ?><br>
                        <small><?= h($entrega['responsavel_cpf']) ?></small>
                    </td>
                    <td><?= h($entrega['tipo_ajuda_nome']) ?></td>
                    <td><?= h(number_format((float) $entrega['quantidade'], 2, ',', '.')) ?> <?= h($entrega['unidade_medida']) ?></td>
                    <td>
                        <a href="<?= h(url('/cadastros/residencias/' . $entrega['residencia_id'])) ?>"><?= h($entrega['protocolo']) ?></a><br>
                        <small><?= h($entrega['bairro_comunidade']) ?> - <?= h($entrega['municipio_nome']) ?>/<?= h($entrega['uf']) ?></small>
                    </td>
                    <td>
                        <?= h(date('d/m/Y H:i', strtotime((string) $entrega['data_entrega']))) ?><br>
                        <small><?= h($entrega['entregue_por_nome']) ?></small>
                    </td>
                    <td class="actions-column">
                        <a href="<?= h(url('/gestor/entregas/' . $entrega['id'] . '/comprovante')) ?>">Comprovante</a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($entregas === []): ?>
                <tr>
                    <td colspan="7" class="empty-state">Nenhuma entrega registrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
