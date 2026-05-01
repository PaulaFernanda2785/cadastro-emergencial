<section class="dashboard-header">
    <div>
        <span class="eyebrow">Cadastro emergencial</span>
        <h1>Familias cadastradas</h1>
        <p>Consulta operacional das familias vinculadas as residencias cadastradas.</p>
    </div>
</section>

<section class="table-panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Responsavel</th>
                <th>CPF</th>
                <th>Contato</th>
                <th>Integrantes</th>
                <th>Residencia</th>
                <th>Acao</th>
                <th>Entregas</th>
                <th class="actions-column">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($familias as $familia): ?>
                <tr>
                    <td><?= h($familia['responsavel_nome']) ?></td>
                    <td><?= h($familia['responsavel_cpf']) ?></td>
                    <td>
                        <?= h($familia['telefone'] ?: '-') ?><br>
                        <small><?= h($familia['email'] ?: '') ?></small>
                    </td>
                    <td><?= h($familia['quantidade_integrantes']) ?></td>
                    <td>
                        <a href="<?= h(url('/cadastros/residencias/' . $familia['residencia_id'])) ?>"><?= h($familia['protocolo']) ?></a><br>
                        <small><?= h($familia['bairro_comunidade']) ?> - <?= h($familia['municipio_nome']) ?>/<?= h($familia['uf']) ?></small>
                    </td>
                    <td><?= h($familia['localidade']) ?> - <?= h($familia['tipo_evento']) ?></td>
                    <td><?= h($familia['entregas_registradas']) ?></td>
                    <td class="actions-column">
                        <a href="<?= h(url('/cadastros/residencias/' . $familia['residencia_id'] . '/familias/' . $familia['id'] . '/comprovante')) ?>">Comprovante</a>
                        <?php if (in_array((string) (current_user()['perfil'] ?? ''), ['gestor', 'administrador'], true)): ?>
                            <a href="<?= h(url('/gestor/familias/' . $familia['id'] . '/entregas/novo')) ?>">Entrega</a>
                        <?php else: ?>
                            <a href="<?= h(url('/cadastros/residencias/' . $familia['residencia_id'])) ?>">Ver</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($familias === []): ?>
                <tr>
                    <td colspan="8" class="empty-state">Nenhuma familia cadastrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
