<?php
$status = (string) ($assinatura['status'] ?? 'pendente');
$statusLabel = [
    'pendente' => 'Pendente',
    'autorizado' => 'Autorizado',
    'negado' => 'Não autorizado',
    'cancelado' => 'Cancelado',
][$status] ?? '-';
$documentTypeLabel = [
    'dti' => 'DTI',
    'prestacao_contas' => 'Prestação de contas',
    'recomecar' => 'Programa Recomeçar',
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
$coSignatureStatus = is_array($coSignatureStatus ?? null) ? $coSignatureStatus : ['solicitacoes' => []];
$documentSignatures = is_array($coSignatureStatus['solicitacoes'] ?? null) ? $coSignatureStatus['solicitacoes'] : [];
$inlineDocumentHtml = trim((string) ($inlineDocumentHtml ?? ''));
$documentUrl = (string) ($assinatura['url_documento'] ?? '');
$documentEmbedUrl = '';
$documentEmbedSrc = '';
$appendQueryParams = static function (string $url, array $params): string {
    foreach ($params as $key => $value) {
        if (preg_match('/(?:^|[?&])' . preg_quote((string) $key, '/') . '=/', $url) === 1) {
            continue;
        }

        $url .= (str_contains($url, '?') ? '&' : '?') . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }

    return $url;
};

if ($documentUrl !== '') {
    $documentEmbedParams = ['embed_document' => '1'];

    if (in_array((string) ($assinatura['documento_tipo'] ?? ''), ['prestacao_contas', 'recomecar'], true)) {
        $documentEmbedParams = ['assinatura' => '1', 'embed_document' => '1'];
    }

    $documentEmbedUrl = $appendQueryParams($documentUrl, $documentEmbedParams);
    $documentEmbedSrc = preg_match('#^https?://#i', $documentEmbedUrl) === 1
        ? $documentEmbedUrl
        : url($documentEmbedUrl);
}
$hasDocumentPreview = $inlineDocumentHtml !== '' || $documentEmbedSrc !== '';
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
            <?php if ($printReady && $hasDocumentPreview): ?>
                <button type="button" class="primary-button" data-signature-print-document>Imprimir / baixar PDF</button>
            <?php endif; ?>
            <a class="secondary-button" href="<?= h(url('/assinaturas')) ?>">Voltar</a>
        </div>
    </header>

    <section class="signature-context-grid" aria-label="Resumo da assinatura">
        <article>
            <span>Status</span>
            <strong><?= h($statusLabel) ?></strong>
            <small>Situação atual da coassinatura.</small>
        </article>
        <article>
            <span>Documento</span>
            <strong><?= h($documentTypeLabel) ?></strong>
            <small>Tipo de documento em análise.</small>
        </article>
        <article>
            <span>Solicitante</span>
            <strong><?= h($assinatura['solicitante_nome'] ?? '-') ?></strong>
            <small>Usuário que solicitou a coassinatura.</small>
        </article>
        <article>
            <span><?= $isPrincipalHistory ? 'Assinante principal' : 'Coautor' ?></span>
            <strong><?= h($assinatura['coautor_nome'] ?? '-') ?></strong>
            <small><?= $isPrincipalHistory ? 'Usuário que assinou e gerou o documento.' : 'Usuário indicado para autorizar.' ?></small>
        </article>
    </section>

    <article class="signature-request-panel">
        <div class="signature-panel-heading">
            <div>
                <span class="eyebrow">Solicitação</span>
                <h2>Dados da solicitação</h2>
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
                        <dt>Não autorizado em</dt>
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
                    <h3>Sua decisão está pendente</h3>
                    <p>Confira o documento abaixo antes de autorizar ou negar a coassinatura.</p>
                <?php elseif ($status === 'pendente' && $isRequester): ?>
                    <h3>Aguardando resposta</h3>
                    <p>O documento permanecerá bloqueado para impressão até a decisão do coautor.</p>
                <?php elseif ($status === 'autorizado'): ?>
                    <h3><?= $isPrincipalHistory ? 'Assinatura principal registrada' : 'Coassinatura autorizada' ?></h3>
                    <p><?= $isPrincipalHistory ? 'Este documento foi assinado pelo usuário principal e ficou registrado no histórico.' : ($printReady ? 'Todas as assinaturas foram autorizadas. A impressão do documento está liberada.' : 'Sua autorização foi registrada. O documento ainda pode depender de outros coautores.') ?></p>
                <?php elseif ($status === 'negado'): ?>
                    <h3>Coassinatura não autorizada</h3>
                    <p>O documento permanece bloqueado enquanto houver negativa ativa.</p>
                <?php else: ?>
                    <h3>Solicitação encerrada</h3>
                    <p>Consulte o documento original para conferir a situação atual.</p>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <p>Perfil administrador: acesso completo ao registro e ao documento para auditoria.</p>
                <?php endif; ?>
            </aside>
        </div>

        <?php if ($documentSignatures !== []): ?>
            <section class="signature-document-signers" aria-label="Assinaturas vinculadas ao documento">
                <div class="signature-panel-heading">
                    <div>
                        <span class="eyebrow">Documento</span>
                        <h2>Assinaturas vinculadas</h2>
                    </div>
                </div>
                <div class="signature-signer-grid">
                    <?php foreach ($documentSignatures as $documentSignature): ?>
                        <?php $isPrimarySigner = (int) ($documentSignature['coautor_usuario_id'] ?? 0) === (int) ($documentSignature['solicitante_usuario_id'] ?? 0); ?>
                        <article>
                            <span><?= h($isPrimarySigner ? 'Assinante principal' : 'Coautor') ?></span>
                            <strong><?= h($documentSignature['coautor_nome'] ?? '-') ?></strong>
                            <small><?= h(['pendente' => 'Pendente', 'autorizado' => 'Autorizado', 'negado' => 'Não autorizado'][$documentSignature['status'] ?? ''] ?? '-') ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isCoauthor && $status === 'pendente'): ?>
            <div class="signature-action-panel">
                <form method="post" action="<?= h(url('/assinaturas/' . (int) $assinatura['id'] . '/autorizar')) ?>" class="js-prevent-double-submit">
                    <?= csrf_field() ?>
                    <?= idempotency_field('assinaturas.authorize.' . (int) $assinatura['id']) ?>
                    <button type="submit" class="primary-button" data-loading-text="Autorizando...">Autorizar assinatura</button>
                </form>

                <form method="post" action="<?= h(url('/assinaturas/' . (int) $assinatura['id'] . '/negar')) ?>" class="js-prevent-double-submit" data-confirm="Confirmar que você não autoriza esta assinatura?">
                    <?= csrf_field() ?>
                    <?= idempotency_field('assinaturas.reject.' . (int) $assinatura['id']) ?>
                    <label class="field">
                        <span>Motivo obrigatório</span>
                        <textarea name="motivo_negativa" rows="3" maxlength="500" required placeholder="Descreva o motivo da não autorização"></textarea>
                    </label>
                    <button type="submit" class="danger-button" data-loading-text="Registrando...">Não autorizar</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($isCoauthor && $status === 'negado'): ?>
            <div class="signature-return-panel">
                <div>
                    <span class="eyebrow">Revisão da decisão</span>
                    <h3>Retornar para assinatura</h3>
                    <p>Use esta ação para voltar a solicitação ao status pendente e registrar uma nova decisão.</p>
                </div>
                <form method="post" action="<?= h(url('/assinaturas/' . (int) $assinatura['id'] . '/retornar-assinatura')) ?>" class="js-prevent-double-submit">
                    <?= csrf_field() ?>
                    <?= idempotency_field('assinaturas.return.' . (int) $assinatura['id']) ?>
                    <button type="submit" class="primary-button" data-loading-text="Retornando...">Retornar para assinar</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($printReady && $hasDocumentPreview): ?>
            <div class="signature-print-ready-panel">
                <div>
                    <span class="eyebrow">Documento liberado</span>
                    <h3>Assinaturas autorizadas</h3>
                    <p>Usuário principal e coautores concluíram a assinatura. A impressão e o salvamento em PDF estão disponíveis para usuários autorizados.</p>
                </div>
                <button type="button" class="primary-button" data-signature-print-document>Imprimir / baixar PDF</button>
            </div>
        <?php endif; ?>
    </article>

    <article class="signature-document-panel">
        <div class="signature-panel-heading">
            <div>
                <span class="eyebrow">Documento</span>
                <h2>Conferência do documento</h2>
            </div>
        </div>

        <div class="signature-document-stage <?= $inlineDocumentHtml !== '' ? 'is-inline-document' : '' ?>" <?= $inlineDocumentHtml !== '' ? 'data-signature-inline-document' : '' ?>>
            <?php if ($inlineDocumentHtml !== ''): ?>
                <?= $inlineDocumentHtml ?>
            <?php elseif ($documentEmbedSrc !== ''): ?>
                <iframe src="<?= h($documentEmbedSrc) ?>" title="Documento para assinatura" scrolling="no" data-signature-document-frame></iframe>
            <?php else: ?>
                <div class="empty-state">Documento sem URL de visualização.</div>
            <?php endif; ?>
        </div>
    </article>
</section>

<script>
    document.querySelectorAll('[data-signature-document-frame]').forEach(function (frame) {
        var resize = function () {
            try {
                var doc = frame.contentDocument || frame.contentWindow.document;
                var documentNode = doc.querySelector('.dti-document');
                var rectHeight = documentNode && documentNode.getBoundingClientRect
                    ? documentNode.getBoundingClientRect().height
                    : 0;
                var height = rectHeight > 0
                    ? Math.ceil(rectHeight + 32)
                    : Math.max(
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
            setTimeout(resize, 1400);
            setTimeout(resize, 2200);
        });
        window.addEventListener('resize', resize);
    });

    document.querySelectorAll('[data-signature-print-document]').forEach(function (button) {
        button.addEventListener('click', function () {
            var frame = document.querySelector('[data-signature-document-frame]');

            if (!frame || !frame.contentWindow) {
                if (document.querySelector('[data-signature-inline-document]')) {
                    document.body.classList.add('is-printing-signature-document');
                    window.print();
                    return;
                }

                window.print();
                return;
            }

            frame.contentWindow.focus();
            frame.contentWindow.print();
        });
    });

    window.addEventListener('afterprint', function () {
        document.body.classList.remove('is-printing-signature-document');
    });
</script>
