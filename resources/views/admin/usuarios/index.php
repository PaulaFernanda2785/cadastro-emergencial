<?php
$filters = $filters ?? ['q' => '', 'perfil' => '', 'status' => '', 'militar' => ''];
$summary = $summary ?? [];
$pagination = $pagination ?? ['page' => 1, 'per_page' => 10, 'total' => count($usuarios), 'total_pages' => 1];
$allUsuarios = $allUsuarios ?? $usuarios;
$profiles = $profiles ?? ['cadastrador', 'gestor', 'administrador'];
$profileLabels = [
    'administrador' => 'Administrador',
    'gestor' => 'Gestor',
    'cadastrador' => 'Cadastrador',
];
$statusLabels = ['ativo' => 'Ativo', 'inativo' => 'Inativo'];
$militarLabels = ['sim' => 'Militar', 'nao' => 'Não militar'];
$totalUsuarios = (int) ($summary['total_usuarios'] ?? $pagination['total'] ?? 0);
$totalAtivos = (int) ($summary['ativos'] ?? 0);
$totalInativos = (int) ($summary['inativos'] ?? 0);
$totalMilitares = (int) ($summary['militares'] ?? 0);
$ultimoAcesso = !empty($summary['ultimo_acesso']) ? strtotime((string) $summary['ultimo_acesso']) : null;
$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$perPage = max(1, (int) ($pagination['per_page'] ?? 10));
$firstRecord = $totalUsuarios > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastRecord = min($totalUsuarios, $page * $perPage);
$activeFilters = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
$filterLabels = ['q' => 'Busca', 'perfil' => 'Perfil', 'status' => 'Status', 'militar' => 'Vínculo'];
$filterValueLabel = static function (string $key, string $value) use ($profileLabels, $statusLabels, $militarLabels): string {
    if ($key === 'perfil') {
        return $profileLabels[$value] ?? $value;
    }
    if ($key === 'status') {
        return $statusLabels[$value] ?? $value;
    }
    if ($key === 'militar') {
        return $militarLabels[$value] ?? $value;
    }

    return $value;
};
$pageUrl = static function (int $targetPage) use ($filters): string {
    $query = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
    $query['pagina'] = max(1, $targetPage);

    return url('/admin/usuarios' . ($query !== [] ? '?' . http_build_query($query) : ''));
};
$currentUserId = (int) (current_user()['id'] ?? 0);
?>

<section class="records-page users-page">
    <header class="action-form-header records-header">
        <div>
            <span class="eyebrow">Administração</span>
            <h1>Usuários</h1>
            <p>Gerencie contas, perfis, status de acesso e credenciais do sistema.</p>
        </div>
        <a class="primary-link-button" href="<?= h(url('/admin/usuarios/novo')) ?>">Novo usuário</a>
    </header>

    <section class="records-summary-grid users-summary-grid" aria-label="Resumo dos usuários">
        <article class="records-summary-card">
            <span>Usuários</span>
            <strong><?= h($totalUsuarios) ?></strong>
            <small><?= $activeFilters === [] ? 'Total cadastrado.' : 'Total encontrado pelos filtros.' ?></small>
        </article>
        <article class="records-summary-card">
            <span>Ativos</span>
            <strong><?= h($totalAtivos) ?></strong>
            <small>Contas liberadas para acesso.</small>
        </article>
        <article class="records-summary-card">
            <span>Inativos</span>
            <strong><?= h($totalInativos) ?></strong>
            <small>Contas bloqueadas para login.</small>
        </article>
        <article class="records-summary-card">
            <span>Militares</span>
            <strong><?= h($totalMilitares) ?></strong>
            <small><?= $ultimoAcesso !== null ? 'Último acesso em ' . h(date('d/m/Y H:i', $ultimoAcesso)) : 'Sem acesso registrado' ?></small>
        </article>
    </section>

    <section class="records-filter-panel users-filter-panel users-filter-modern-panel" aria-label="Filtros de usuários">
        <form method="get" action="<?= h(url('/admin/usuarios')) ?>" class="users-filter-form users-filter-modern-form">
            <label class="field styled-field users-search-field users-filter-field users-filter-field-wide">
                <span>Busca inteligente</span>
                <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" maxlength="120" list="users-search-list" placeholder="Nome, CPF, e-mail, órgão, setor ou MF">
                <datalist id="users-search-list">
                    <?php foreach ($allUsuarios as $usuarioOpcao): ?>
                        <option value="<?= h($usuarioOpcao['nome']) ?>"></option>
                        <option value="<?= h($usuarioOpcao['email']) ?>"></option>
                        <option value="<?= h($usuarioOpcao['cpf']) ?>"></option>
                        <?php if (!empty($usuarioOpcao['matricula_funcional'])): ?>
                            <option value="<?= h($usuarioOpcao['matricula_funcional']) ?>"></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label class="field styled-field users-filter-field users-filter-field-compact">
                <span>Perfil</span>
                <select name="perfil">
                    <option value="">Todos</option>
                    <?php foreach ($profiles as $profile): ?>
                        <option value="<?= h($profile) ?>" <?= (string) ($filters['perfil'] ?? '') === (string) $profile ? 'selected' : '' ?>><?= h($profileLabels[$profile] ?? ucfirst($profile)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field users-filter-field users-filter-field-compact">
                <span>Status</span>
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['status'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field styled-field users-filter-field users-filter-field-compact">
                <span>Vínculo</span>
                <select name="militar">
                    <option value="">Todos</option>
                    <?php foreach ($militarLabels as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (string) ($filters['militar'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="records-filter-actions users-filter-actions">
                <button type="submit" class="primary-button">Filtrar</button>
                <a class="secondary-button" href="<?= h(url('/admin/usuarios')) ?>">Limpar</a>
            </div>
        </form>

        <?php if ($activeFilters !== []): ?>
            <div class="records-active-filters" aria-label="Filtros ativos">
                <?php foreach ($activeFilters as $key => $value): ?>
                    <span><?= h(($filterLabels[$key] ?? $key) . ': ' . $filterValueLabel((string) $key, (string) $value)) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="records-list-toolbar">
        <span><?= h($totalUsuarios) ?> usuário(s) encontrado(s)</span>
        <strong><?= h($firstRecord) ?>-<?= h($lastRecord) ?> de <?= h($totalUsuarios) ?></strong>
    </div>

    <?php if ($usuarios === []): ?>
        <section class="action-empty-panel records-empty-panel">
            <h2><?= $activeFilters === [] ? 'Nenhum usuário cadastrado' : 'Nenhum usuário encontrado' ?></h2>
            <p><?= $activeFilters === [] ? 'Crie a primeira conta administrativa ou operacional.' : 'Revise os filtros aplicados ou limpe a busca.' ?></p>
            <?php if ($activeFilters !== []): ?>
                <a class="primary-link-button" href="<?= h(url('/admin/usuarios')) ?>">Limpar filtros</a>
            <?php else: ?>
                <a class="primary-link-button" href="<?= h(url('/admin/usuarios/novo')) ?>">Criar primeiro usuário</a>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="user-card-list" aria-label="Lista de usuários">
            <?php foreach ($usuarios as $usuario): ?>
                <?php
                $ativo = (int) ($usuario['ativo'] ?? 0) === 1;
                $isSelf = (int) ($usuario['id'] ?? 0) === $currentUserId;
                $ultimo = !empty($usuario['ultimo_acesso']) ? strtotime((string) $usuario['ultimo_acesso']) : false;
                $criado = !empty($usuario['criado_em']) ? strtotime((string) $usuario['criado_em']) : false;
                ?>
                <article class="user-card">
                    <div class="user-card-main">
                        <div class="user-card-title">
                            <span class="status <?= $ativo ? 'status-aberta' : 'status-cancelada' ?>"><?= $ativo ? 'Ativo' : 'Inativo' ?></span>
                            <h2><?= h($usuario['nome']) ?></h2>
                            <p><?= h($usuario['email']) ?></p>
                        </div>

                        <dl class="user-card-meta">
                            <div><dt>CPF</dt><dd><?= h($usuario['cpf']) ?></dd></div>
                            <div><dt>Perfil</dt><dd><?= h($profileLabels[$usuario['perfil']] ?? ucfirst((string) $usuario['perfil'])) ?></dd></div>
                            <div><dt>Telefone</dt><dd><?= h($usuario['telefone'] ?: '-') ?></dd></div>
                            <div><dt>Órgão</dt><dd><?= h($usuario['orgao'] ?: '-') ?></dd></div>
                            <div><dt>Unidade</dt><dd><?= h($usuario['unidade_setor'] ?: '-') ?></dd></div>
                            <div><dt>Último acesso</dt><dd><?= $ultimo !== false ? h(date('d/m/Y H:i', $ultimo)) : '-' ?></dd></div>
                            <?php if (!empty($usuario['militar'])): ?>
                                <div><dt>Militar</dt><dd><?= h(trim(($usuario['graduacao'] ?: '') . ' ' . ($usuario['nome_guerra'] ?: '')) ?: 'Sim') ?></dd></div>
                                <div><dt>MF</dt><dd><?= h($usuario['matricula_funcional'] ?: '-') ?></dd></div>
                            <?php endif; ?>
                            <div><dt>Criado em</dt><dd><?= $criado !== false ? h(date('d/m/Y H:i', $criado)) : '-' ?></dd></div>
                        </dl>
                    </div>

                    <aside class="user-card-actions" aria-label="Ações do usuário">
                        <a class="secondary-button action-card-button" href="<?= h(url('/admin/usuarios/' . $usuario['id'] . '/editar')) ?>">Editar dados</a>
                        <a class="secondary-button action-card-button" href="<?= h(url('/admin/usuarios/' . $usuario['id'] . '/senha')) ?>">Senha</a>

                        <?php if ($ativo): ?>
                            <?php if ($isSelf): ?>
                                <span class="action-card-disabled">Inativar bloqueado</span>
                            <?php else: ?>
                                <form method="post" action="<?= h(url('/admin/usuarios/' . $usuario['id'] . '/inativar')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Inativar este usuário? Ele não conseguirá acessar o sistema.">
                                    <?= csrf_field() ?>
                                    <?= idempotency_field('admin.usuarios.status.' . $usuario['id'] . '.inativar') ?>
                                    <button type="submit" class="secondary-button action-card-button" data-loading-text="Inativando...">Inativar</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <form method="post" action="<?= h(url('/admin/usuarios/' . $usuario['id'] . '/ativar')) ?>" class="inline-form js-prevent-double-submit">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.usuarios.status.' . $usuario['id'] . '.ativar') ?>
                                <button type="submit" class="secondary-button action-card-button" data-loading-text="Ativando...">Ativar</button>
                            </form>
                        <?php endif; ?>
                    </aside>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="records-pagination" aria-label="Paginação dos usuários">
            <a class="pagination-link <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= h($page > 1 ? $pageUrl($page - 1) : '#') ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Anterior</a>

            <div class="pagination-pages">
                <?php for ($itemPage = 1; $itemPage <= $totalPages; $itemPage++): ?>
                    <?php if ($itemPage === 1 || $itemPage === $totalPages || abs($itemPage - $page) <= 1): ?>
                        <a class="pagination-number <?= $itemPage === $page ? 'is-active' : '' ?>" href="<?= h($pageUrl($itemPage)) ?>" aria-current="<?= $itemPage === $page ? 'page' : 'false' ?>"><?= h($itemPage) ?></a>
                    <?php elseif ($itemPage === 2 && $page > 3 || $itemPage === $totalPages - 1 && $page < $totalPages - 2): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>

            <a class="pagination-link <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h($page < $totalPages ? $pageUrl($page + 1) : '#') ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Próxima</a>
        </nav>
    <?php endif; ?>
</section>
