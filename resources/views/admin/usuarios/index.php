<section class="dashboard-header">
    <div>
        <span class="eyebrow">Administracao</span>
        <h1>Usuarios</h1>
        <p>Gerencie contas, perfis e status de acesso ao sistema.</p>
    </div>
    <a class="primary-link-button" href="<?= h(url('/admin/usuarios/novo')) ?>">Novo usuario</a>
</section>

<section class="table-panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>CPF</th>
                <th>Perfil</th>
                <th>Status</th>
                <th>Ultimo acesso</th>
                <th class="actions-column">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td>
                        <?= h($usuario['nome']) ?><br>
                        <small><?= h($usuario['orgao'] ?: '-') ?> <?= h($usuario['unidade_setor'] ?: '') ?></small>
                        <?php if (!empty($usuario['matricula_funcional'])): ?>
                            <br><small>MF <?= h($usuario['matricula_funcional']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= h($usuario['email']) ?></td>
                    <td><?= h($usuario['cpf']) ?></td>
                    <td><?= h(ucfirst((string) $usuario['perfil'])) ?></td>
                    <td><span class="status status-<?= (int) $usuario['ativo'] === 1 ? 'open' : 'closed' ?>"><?= (int) $usuario['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
                    <td><?= !empty($usuario['ultimo_acesso']) ? h(date('d/m/Y H:i', strtotime((string) $usuario['ultimo_acesso']))) : '-' ?></td>
                    <td class="actions-column"><a href="<?= h(url('/admin/usuarios/' . $usuario['id'] . '/editar')) ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>

            <?php if ($usuarios === []): ?>
                <tr>
                    <td colspan="7" class="empty-state">Nenhum usuario cadastrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
