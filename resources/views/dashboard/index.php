<section class="dashboard-header">
    <div>
        <span class="eyebrow">Operacao</span>
        <h1><?= h($title) ?></h1>
        <p><?= h($user['nome'] ?? 'Usuario') ?> - perfil <?= h($user['perfil'] ?? '-') ?></p>
    </div>
</section>

<section class="indicator-grid" aria-label="Indicadores iniciais">
    <article class="indicator">
        <span>Residencias</span>
        <strong><?= h($indicators['residencias'] ?? 0) ?></strong>
        <small>Casas atingidas cadastradas.</small>
    </article>
    <article class="indicator">
        <span>Familias</span>
        <strong><?= h($indicators['familias'] ?? 0) ?></strong>
        <small>Vinculadas as residencias cadastradas.</small>
    </article>
    <article class="indicator">
        <span>Tipos de ajuda</span>
        <strong><?= h($indicators['tipos_ajuda'] ?? 0) ?></strong>
        <small>Materiais ativos para entregas.</small>
    </article>
    <article class="indicator">
        <span>Entregas</span>
        <strong><?= h($indicators['entregas'] ?? 0) ?></strong>
        <small>Ajuda humanitaria registrada.</small>
    </article>
    <article class="indicator">
        <span>Acoes abertas</span>
        <strong><?= h($indicators['acoes_abertas'] ?? 0) ?></strong>
        <small>Com tokens publicos para QR Code.</small>
    </article>
</section>

<section class="module-list">
    <h2>Modulos administrativos</h2>
    <ul>
        <?php $activeActionToken = App\Core\Session::get('active_action_token'); ?>
        <?php if (is_string($activeActionToken) && $activeActionToken !== ''): ?>
            <li><a href="<?= h(url('/acao/' . rawurlencode($activeActionToken) . '/residencias/novo')) ?>">Continuar cadastro da ação aberta acessada pelo QR Code.</a></li>
        <?php endif; ?>
        <?php if (($user['perfil'] ?? '') === 'administrador'): ?>
            <li><a href="<?= h(url('/admin/acoes')) ?>">Gerenciar acoes emergenciais e links publicos.</a></li>
            <li><a href="<?= h(url('/admin/ajudas')) ?>">Gerenciar tipos de ajuda humanitaria.</a></li>
        <?php endif; ?>
        <li><a href="<?= h(url('/cadastros/residencias')) ?>">Consultar residencias e familias cadastradas.</a></li>
        <?php if (in_array((string) ($user['perfil'] ?? ''), ['gestor', 'administrador'], true)): ?>
            <li><a href="<?= h(url('/gestor/entregas')) ?>">Consultar entregas de ajuda humanitaria.</a></li>
            <li><a href="<?= h(url('/gestor/prestacao-contas')) ?>">Gerar prestacao de contas por tipo de ajuda.</a></li>
            <li><a href="<?= h(url('/gestor/relatorios')) ?>">Acompanhar relatorios operacionais e pendencias.</a></li>
            <li><a href="<?= h(url('/gestor/recomecar')) ?>">Recomecar o contexto operacional da sessao.</a></li>
        <?php endif; ?>
    </ul>
</section>
