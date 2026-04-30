<?php
$statusLabels = [
    'aberta' => 'Aberta',
    'encerrada' => 'Encerrada',
    'cancelada' => 'Cancelada',
];
$isAdmin = (current_user()['perfil'] ?? '') === 'administrador';
?>

<section class="actions-page">
    <header class="action-form-header">
        <div>
            <span class="eyebrow">Administração</span>
            <h1>Ações emergenciais</h1>
            <p>Gerencie ações, controle o status de atendimento e compartilhe o aplicativo de cadastro por QR Code.</p>
        </div>
        <a class="primary-link-button" href="<?= h(url('/admin/acoes/novo')) ?>">Nova ação</a>
    </header>

    <?php if ($acoes === []): ?>
        <section class="action-empty-panel">
            <h2>Nenhuma ação emergencial cadastrada</h2>
            <p>Crie uma nova ação para liberar o cadastro de residências e famílias por sistema ou QR Code.</p>
            <a class="primary-link-button" href="<?= h(url('/admin/acoes/novo')) ?>">Criar primeira ação</a>
        </section>
    <?php else: ?>
        <section class="action-card-grid" aria-label="Lista de ações emergenciais">
            <?php foreach ($acoes as $acao): ?>
                <?php
                $status = (string) $acao['status'];
                $cadastroUrl = public_url('/acao/' . $acao['token_publico'] . '/residencias/novo');
                ?>
                <article class="action-card">
                    <div class="action-card-main">
                        <div class="action-card-title">
                            <span class="status status-<?= h($status) ?>"><?= h($statusLabels[$status] ?? ucfirst($status)) ?></span>
                            <h2><?= h($acao['localidade']) ?></h2>
                            <p><?= h($acao['municipio_nome']) ?> / <?= h($acao['uf']) ?></p>
                        </div>

                        <dl class="action-card-meta">
                            <div>
                                <dt>Evento</dt>
                                <dd><?= h($acao['tipo_evento']) ?></dd>
                            </div>
                            <div>
                                <dt>Data</dt>
                                <dd><?= h(date('d/m/Y', strtotime((string) $acao['data_evento']))) ?></dd>
                            </div>
                            <div>
                                <dt>Cadastro</dt>
                                <dd><?= h(date('d/m/Y H:i', strtotime((string) $acao['criado_em']))) ?></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="action-card-actions">
                        <button
                            type="button"
                            class="secondary-button action-qr-button"
                            data-action-qr-open
                            data-title="<?= h($acao['localidade']) ?>"
                            data-register-url="<?= h($cadastroUrl) ?>"
                        >
                            Link e QR Code
                        </button>

                        <a class="secondary-button action-card-button" href="<?= h(url('/admin/acoes/' . $acao['id'] . '/editar')) ?>">Editar</a>

                        <?php if ($status !== 'aberta'): ?>
                            <form method="post" action="<?= h(url('/admin/acoes/' . $acao['id'] . '/ativar')) ?>" class="inline-form js-prevent-double-submit">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.acoes.status.' . $acao['id'] . '.aberta') ?>
                                <button type="submit" class="secondary-button action-card-button" data-loading-text="Ativando...">Ativar</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($status !== 'encerrada'): ?>
                            <form method="post" action="<?= h(url('/admin/acoes/' . $acao['id'] . '/encerrar')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Encerrar esta ação? Novos cadastros pelo QR Code serão bloqueados.">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.acoes.status.' . $acao['id'] . '.encerrada') ?>
                                <button type="submit" class="secondary-button action-card-button" data-loading-text="Encerrando...">Encerrar</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($status !== 'cancelada'): ?>
                            <form method="post" action="<?= h(url('/admin/acoes/' . $acao['id'] . '/cancelar')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Cancelar esta ação? Novos cadastros pelo QR Code serão bloqueados.">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.acoes.status.' . $acao['id'] . '.cancelada') ?>
                                <button type="submit" class="secondary-button action-card-button" data-loading-text="Cancelando...">Cancelar</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($isAdmin): ?>
                            <form method="post" action="<?= h(url('/admin/acoes/' . $acao['id'] . '/excluir')) ?>" class="inline-form js-prevent-double-submit" data-confirm="Excluir esta ação da listagem? O registro continuará preservado no banco.">
                                <?= csrf_field() ?>
                                <?= idempotency_field('admin.acoes.delete.' . $acao['id']) ?>
                                <button type="submit" class="danger-button action-card-button" data-loading-text="Excluindo...">Excluir</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</section>

<dialog class="qr-modal" data-action-qr-modal aria-labelledby="qr-modal-title">
    <form method="dialog" class="qr-modal-close-form">
        <button type="submit" class="qr-modal-close" aria-label="Fechar">×</button>
    </form>
    <div class="qr-modal-content">
        <span class="eyebrow">Aplicativo de cadastro</span>
        <h2 id="qr-modal-title" data-action-qr-title>QR Code da ação</h2>
        <p>Leia o QR Code para iniciar o cadastro desta ação.</p>
        <canvas class="qr-modal-image" data-action-qr-canvas aria-label="QR Code do aplicativo de cadastro"></canvas>
        <div class="qr-modal-actions">
            <a class="primary-link-button" href="#" target="_blank" rel="noopener" data-action-qr-register>Cadastrar pelo sistema</a>
        </div>
    </div>
</dialog>
