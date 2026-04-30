<section class="dashboard-header">
    <div>
        <span class="eyebrow">Cadastros</span>
        <h1>Residencias cadastradas</h1>
        <p>Acompanhamento das casas atingidas e das familias vinculadas.</p>
    </div>
</section>

<section class="table-panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Protocolo</th>
                <th>Municipio</th>
                <th>Acao</th>
                <th>Bairro/comunidade</th>
                <th>Familias</th>
                <th>Cadastrador</th>
                <th class="actions-column">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($residencias as $residencia): ?>
                <tr>
                    <td><?= h($residencia['protocolo']) ?></td>
                    <td><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?></td>
                    <td><?= h($residencia['localidade']) ?> - <?= h($residencia['tipo_evento']) ?></td>
                    <td><?= h($residencia['bairro_comunidade']) ?></td>
                    <td><?= h($residencia['familias_cadastradas']) ?> / <?= h($residencia['quantidade_familias']) ?></td>
                    <td><?= h($residencia['cadastrador_nome']) ?></td>
                    <td class="actions-column"><a href="<?= h(url('/cadastros/residencias/' . $residencia['id'])) ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>

            <?php if ($residencias === []): ?>
                <tr>
                    <td colspan="7" class="empty-state">Nenhuma residencia cadastrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
