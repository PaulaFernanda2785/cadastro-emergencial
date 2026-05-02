<?php
$status = (string) ($assinatura['status'] ?? 'pendente');
$statusLabel = [
    'pendente' => 'Pendente',
    'autorizado' => 'Autorizado',
    'negado' => 'Nao autorizado',
    'cancelado' => 'Cancelado',
][$status] ?? '-';
$documentTypeLabel = [
    'dti' => 'DTI',
    'prestacao_contas' => 'Prestacao de contas',
][(string) ($assinatura['documento_tipo'] ?? '')] ?? (string) ($assinatura['documento_tipo'] ?? '-');
$formatDateTime = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '-';
};
$isCoauthor = (int) ($assinatura['coautor_usuario_id'] ?? 0) === (int) (current_user()['id'] ?? 0);
$isRequester = (int) ($assinatura['solicitante_usuario_id'] ?? 0) === (int) (current_user()['id'] ?? 0);
$isPrincipalHistory = (int) ($assinatura['coautor_usuario_id'] ?? 0) === (int) ($assinatura['solicitante_usuario_id'] ?? 0);
$printReady = (bool) ($printReady ?? false);
$isAdmin = (string) (current_user()['perfil'] ?? '') === 'administrador';
$documentUrl = (string) ($assinatura['url_documento'] ?? '');
$documentEmbedUrl = '';

if ($documentUrl !== '') {
    $separator = str_contains($documentUrl, '?') ? '&' : '?';
    $documentEmbedUrl = $documentUrl . $separator . 'embed_document=1';
}
?>

<section class="records-page signature-detail-page">
    <header class="signature-detail-hero">
        <div>
            <span class="eyebrow">Assinatura digital</span>
            <h1><?= h($assinatura['titulo'] ?? 'Documento') ?></h1>
            <p><?= h($assinatura['descricao'] ?? 'Documento aguardando acompanhamento de assinatura.') ?></p>
        </div>
        <div class="signature-hero-actions">
            <span class="status-pill status-<?= h($status) ?>"><?= h($statusLabel) ?></span>
            <?php if ($printReady && $documentEmbedUrl !== ''): ?>
                <button type="button" class="primary-button" data-signature-print-document>Imprimir / baixar PDF</button>
            <?php endif; ?>
            <a class="secondary-button" href="<?= h(url('/assinaturas')) ?>">Voltar</a>
        </div>
    </header>

    <section class="signature-context-grid" aria-label="Resumo da assinatura">
        <article>
            <span>Status</span>
            <strong><?= h($statusLabel) ?></strong>
            <small>Situacao atual da coassinatura.</small>
        </article>
        <article>
            <span>Documento</span>
            <strong><?= h($documentTypeLabel) ?></strong>
            <small>Tipo de documento em analise.</small>
        </article>
        <article>
            <span>Solicitante</span>
            <strong><?= h($assinatura['solicitante_nome'] ?? '-') ?></strong>
            <small>Usuario que solicitou a coassinatura.</small>
        </article>
        <article>
            <span><?= $isPrincipalHistory ? 'Assinante principal' : 'Coautor' ?></span>
            <strong><?= h($assinatura['coautor_nome'] ?? '-') ?></strong>
            <small><?= $isPrincipalHistory ? 'Usuario que assinou e gerou o documento.' : 'Usuario indicado para autorizar.' ?></small>
        </article>
    </section>

    <article class="signature-request-panel">
        <div class="signature-panel-heading">
            <div>
                <span class="eyebrow">Solicitacao</span>
                <h2>Dados da solicitacao</h2>
            </div>
            <span class="status-pill status-<?= h($status) ?>"><?= h($statusLabel) ?></span>
        </div>

        <div class="signature-request-body">
            <dl class="signature-data-grid">
                <div>
                    <dt>Solicitante</dt>
                    <dd><?= h($assinatura['solicitante_nome'] ?? '-') ?></dd>
                </div>
                <div>
                    <dt>CPF do solicitante</dt>
                    <dd><?= h($assinatura['solicitante_cpf'] ?? '-') ?></dd>
                </div>
                <div>
                    <dt><?= $isPrincipalHistory ? 'Assinante principal' : 'Coautor' ?></dt>
                    <dd><?= h($assinatura['coautor_nome'] ?? '-') ?></dd>
                </div>
                <div>
                    <dt><?= $isPrincipalHistory ? 'CPF do assinante' : 'CPF do coautor' ?></dt>
                    <dd><?= h($assinatura['coautor_cpf'] ?? '-') ?></dd>
                </div>
                <div>
                    <dt>Tipo de documento</dt>
                    <dd><?= h($documentTypeLabel) ?></dd>
                </div>
                <div>
                    <dt>Solicitado em</dt>
                    <dd><?= h($formatDateTime($assinatura['solicitado_em'] ?? '')) ?></dd>
                </div>
                <?php if ($status === 'autorizado'): ?>
                    <div>
                        <dt>Autorizado em</dt>
                        <dd><?= h($formatDateTime($assinatura['autorizado_em'] ?? '')) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($status === 'negado'): ?>
                    <div>
                        <dt>Nao autorizado em</dt>
                        <dd><?= h($formatDateTime($assinatura['negado_em'] ?? '')) ?></dd>
                    </div>
                    <div class="signature-data-wide">
                        <dt>Motivo informado</dt>
                        <dd><?= h($assinatura['motivo_negativa'] ?? '-') ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <aside class="signature-status-panel">
                <span class="eyebrow">Fluxo</span>
                <?php if ($status === 'pendente' && $isCoauthor): ?>
                    <h3>Sua decisao esta pendente</h3>
                    <p>Confira o documento abaixo antes de autorizar ou negar a coassinatura.</p>
                <?php elseif ($status === 'pendente' && $isRequester): ?>
                    <h3>Aguardando resposta</h3>
                    <p>O documento permanecera bloqueado para impressao ate a decisao do coautor.</p>
                <?php elseif ($status === 'autorizado'): ?>
                    <h3><?= $isPrincipalHistory ? 'Assinatura principal registrada' : 'Coassinatura autorizada' ?></h3>
                    <p><?= $isPrincipalHistory ? 'Este documento foi assinado pelo usuario principal e ficou registrado no historico.' : ($printReady ? 'Todas as assinaturas foram autorizadas. A impressao do documento esta liberada.' : 'Sua autorizacao foi registrada. O documento ainda pode depender de outros coautores.') ?></p>
                <?php elseif ($status === 'negado'): ?>
                    <h3>Coassinatura nao autorizada</h3>
                    <p>O documento permanece bloqueado enquanto houver negativa ativa.</p>
                <?php else: ?>
                    <h3>Solicitacao encerrada</h3>
                    <p>Consulte o documento original para conferir a situacao atual.</p>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <p>Perfil administrador: acesso completo ao registro e ao documento para auditoria.</p>
                <?php endif; ?>
            </aside>
        </div>

        <?php if ($isCoauthor && $status === 'pendente'): ?>
            <div class="signature-action-panel">
                <form method="post" action="<?= h(url('/assinaturas/' . (int) $assinatura['id'] . '/autorizar')) ?>" class="js-prevent-double-submit">
                    <?= csrf_field() ?>
                    <?= idempotency_field('assinaturas.authorize.' . (int) $assinatura['id']) ?>
                    <button type="submit" class="primary-button" data-loading-text="Autorizando...">Autorizar assinatura</button>
                </form>

                <form method="post" action="<?= h(url('/assinaturas/' . (int) $assinatura['id'] . '/negar')) ?>" class="js-prevent-double-submit" data-confirm="Confirmar que voce nao autoriza esta assinatura?">
                    <?= csrf_field() ?>
                    <?= idempotency_field('assinaturas.reject.' . (int) $assinatura['id']) ?>
                    <label class="field">
                        <span>Motivo obrigatorio</span>
                        <textarea name="motivo_negativa" rows="3" maxlength="500" required placeholder="Descreva o motivo da nao autorizacao"></textarea>
                    </label>
                    <button type="submit" class="danger-button" data-loading-text="Registrando...">Nao autorizar</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($isCoauthor && $status === 'negado'): ?>
            <div class="signature-return-panel">
                <div>
                    <span class="eyebrow">Revisao da decisao</span>
                    <h3>Retornar para assinatura</h3>
                    <p>Use esta acao para voltar a solicitacao ao status pendente e registrar uma nova decisao.</p>
                </div>
                <form method="post" action="<?= h(url('/assinaturas/' . (int) $assinatura['id'] . '/retornar-assinatura')) ?>" class="js-prevent-double-submit">
                    <?= csrf_field() ?>
                    <?= idempotency_field('assinaturas.return.' . (int) $assinatura['id']) ?>
                    <button type="submit" class="primary-button" data-loading-text="Retornando...">Retornar para assinar</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($printReady && $documentEmbedUrl !== ''): ?>
            <div class="signature-print-ready-panel">
                <div>
                    <span class="eyebrow">Documento liberado</span>
                    <h3>Assinaturas autorizadas</h3>
                    <p>Usuario principal e coautores concluiram a assinatura. A impressao e o salvamento em PDF estao disponiveis para usuarios autorizados.</p>
                </div>
                <button type="button" class="primary-button" data-signature-print-document>Imprimir / baixar PDF</button>
            </div>
        <?php endif; ?>
    </article>

    <article class="signature-document-panel">
        <div class="signature-panel-heading">
            <div>
                <span class="eyebrow">Documento</span>
                <h2>Conferencia do documento</h2>
            </div>
        </div>

        <div class="signature-document-stage">
            <?php if ($documentEmbedUrl !== ''): ?>
                <iframe src="<?= h(url($documentEmbedUrl)) ?>" title="Documento para assinatura" scrolling="no" data-signature-document-frame></iframe>
            <?php else: ?>
                <div class="empty-state">Documento sem URL de visualizacao.</div>
            <?php endif; ?>
        </div>
    </article>
</section>

<script>
    document.querySelectorAll('[data-signature-document-frame]').forEach(function (frame) {
        var resize = function () {
            try {
                var doc = frame.contentDocument || frame.contentWindow.document;
                var height = Math.max(
                    doc.body ? doc.body.scrollHeight : 0,
                    doc.documentElement ? doc.documentElement.scrollHeight : 0
                );
                if (height > 0) {
                    frame.style.height = Math.ceil(height) + 'px';
                }
            } catch (error) {
            }
        };

        frame.addEventListener('load', function () {
            resize();
            setTimeout(resize, 250);
            setTimeout(resize, 800);
        });
        window.addEventListener('resize', resize);
    });

    document.querySelectorAll('[data-signature-print-document]').forEach(function (button) {
        button.addEventListener('click', function () {
            var frame = document.querySelector('[data-signature-document-frame]');

            if (!frame || !frame.contentWindow) {
                window.print();
                return;
            }

            frame.contentWindow.focus();
            frame.contentWindow.print();
        });
    });
</script>
