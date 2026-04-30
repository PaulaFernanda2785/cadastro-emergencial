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
        <strong>0</strong>
        <small>Modulo preparado para a proxima entrega.</small>
    </article>
    <article class="indicator">
        <span>Familias</span>
        <strong>0</strong>
        <small>Vinculadas as residencias cadastradas.</small>
    </article>
    <article class="indicator">
        <span>Entregas</span>
        <strong>0</strong>
        <small>Controle por tipo de ajuda humanitaria.</small>
    </article>
    <article class="indicator">
        <span>Acoes abertas</span>
        <strong>0</strong>
        <small>Com tokens publicos para QR Code.</small>
    </article>
</section>

<section class="module-list">
    <h2>Modulos administrativos</h2>
    <ul>
        <li><a href="<?= h(url('/admin/acoes')) ?>">Gerenciar acoes emergenciais e links publicos.</a></li>
        <li><a href="<?= h(url('/admin/ajudas')) ?>">Gerenciar tipos de ajuda humanitaria.</a></li>
        <li>Cadastros de residencias e familias entram na proxima fatia.</li>
    </ul>
</section>
