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
    <h2>Primeira entrega instalada</h2>
    <ul>
        <li>Arquitetura MVC customizada em PHP puro.</li>
        <li>Autenticacao com senha hash, sessao segura e middleware de perfil.</li>
        <li>Protecao CSRF e token de idempotencia com janela de 5 segundos.</li>
        <li>Schema base para acoes, residencias, familias, entregas, anexos e logs.</li>
    </ul>
</section>
