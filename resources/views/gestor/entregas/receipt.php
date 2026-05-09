<?php
$statusOperacional = (string) ($entrega['status_operacional'] ?? 'entregue');
$isRegisteredReceipt = $statusOperacional === 'registrado';
$receiptLabel = $isRegisteredReceipt ? 'Comprovante de registro' : 'Comprovante de entrega';
$receiptTitle = $isRegisteredReceipt ? 'Comprovante de Registro' : 'Comprovante de Entrega';
$receiptDate = $isRegisteredReceipt
    ? (string) ($entrega['registrado_em'] ?? $entrega['data_entrega'] ?? '')
    : (string) ($entrega['entregue_em'] ?? $entrega['data_entrega'] ?? '');
?>
<section class="dashboard-header no-print receipt-preview-header">
    <div>
        <span class="eyebrow"><?= h($receiptLabel) ?></span>
        <h1>Pré-visualização do ticket</h1>
        <p><?= h($entrega['responsavel_nome']) ?> - <?= h($entrega['comprovante_codigo']) ?></p>
    </div>
</section>

<section class="receipt-preview-shell">
    <section class="receipt-actions no-print">
        <a class="secondary-button" href="<?= h(url('/gestor/entregas')) ?>">Voltar para entregas</a>
        <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $entrega['residencia_id'])) ?>">Ver residência</a>
        <button
            type="button"
            class="secondary-button"
            data-family-receipt-share
        >Enviar para WhatsApp</button>
        <form method="post" action="<?= h(url('/gestor/entregas/' . $entrega['id'] . '/comprovante/email')) ?>" class="inline-form js-prevent-double-submit">
            <?= csrf_field() ?>
            <?= idempotency_field('gestor.entregas.receipt.email.' . (int) $entrega['id']) ?>
            <button type="submit" class="secondary-button" data-loading-text="Enviando...">Enviar por e-mail</button>
        </form>
        <button type="button" class="primary-button" data-receipt-print>Imprimir ticket</button>
        <span class="receipt-share-status" data-family-receipt-share-status></span>
    </section>

<?php
$whatsappTargetType = (string) ($whatsappTarget['tipo'] ?? '');
$whatsappTargetLabel = $whatsappTargetType === 'representante' ? 'representante familiar' : 'responsavel familiar';
?>

<article
    class="receipt-ticket family-receipt-ticket"
    data-family-receipt-ticket
    data-receipt-code="<?= h($entrega['comprovante_codigo']) ?>"
    data-share-title="<?= h($receiptLabel) ?>"
    data-whatsapp-app-url="<?= h($whatsappAppUrl ?? '') ?>"
    data-whatsapp-url="<?= h($whatsappUrl ?? '') ?>"
    data-whatsapp-target-label="<?= h($whatsappTargetLabel) ?>"
    data-whatsapp-target-name="<?= h($whatsappTarget['nome'] ?? '') ?>"
    data-whatsapp-fallback-app-url="<?= h($whatsappFallbackAppUrl ?? '') ?>"
    data-whatsapp-fallback-url="<?= h($whatsappFallbackUrl ?? '') ?>"
    data-whatsapp-fallback-name="<?= h($whatsappTarget['fallback_nome'] ?? '') ?>"
>
    <div class="receipt-paper-edge" aria-hidden="true"></div>
    <header class="receipt-header">
        <strong>Cadastro Emergencial</strong>
        <span>CEDEC-PA</span>
        <span><?= h($receiptTitle) ?></span>
    </header>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Código</dt>
            <dd><?= h($entrega['comprovante_codigo']) ?></dd>
        </div>
        <div>
            <dt><?= $isRegisteredReceipt ? 'Registro' : 'Entrega' ?></dt>
            <dd><?= h(strtotime($receiptDate) !== false ? date('d/m/Y H:i', strtotime($receiptDate)) : '-') ?></dd>
        </div>
        <div>
            <dt>Protocolo</dt>
            <dd><?= h($entrega['protocolo']) ?></dd>
        </div>
        <div>
            <dt>Município</dt>
            <dd><?= h($entrega['municipio_nome']) ?>/<?= h($entrega['uf']) ?></dd>
        </div>
        <div>
            <dt>Localidade</dt>
            <dd><?= h($entrega['localidade']) ?></dd>
        </div>
        <div>
            <dt>Evento</dt>
            <dd><?= h($entrega['tipo_evento']) ?></dd>
        </div>
    </dl>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Responsável</dt>
            <dd><?= h($entrega['responsavel_nome']) ?></dd>
        </div>
        <div>
            <dt>CPF</dt>
            <dd><?= h($entrega['responsavel_cpf']) ?></dd>
        </div>
        <div>
            <dt>Integrantes</dt>
            <dd><?= h($entrega['quantidade_integrantes']) ?></dd>
        </div>
        <div>
            <dt>Endereço</dt>
            <dd><?= h($entrega['endereco']) ?><?= !empty($entrega['complemento']) ? ' - ' . h($entrega['complemento']) : '' ?></dd>
        </div>
        <div>
            <dt>Bairro</dt>
            <dd><?= h($entrega['bairro_comunidade']) ?></dd>
        </div>
    </dl>

    <div class="receipt-separator"></div>

    <table class="receipt-items">
        <thead>
            <tr>
                <th>Item</th>
                <th>Qtd.</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($entrega['itens'] ?? []) as $item): ?>
                <tr>
                    <td>
                        <?= h($item['tipo_ajuda_nome']) ?>
                        <?php if (!empty($item['observacao'])): ?>
                            <small class="receipt-item-note">Obs.: <?= h($item['observacao']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= h(number_format((float) $item['quantidade'], 2, ',', '.')) ?> <?= h($item['unidade_medida']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt><?= $isRegisteredReceipt ? 'Registrado por' : 'Entregue por' ?></dt>
            <dd><?= h($entrega['entregue_por_nome']) ?></dd>
        </div>
        <div>
            <dt>Emitido em</dt>
            <dd><?= h($generatedAt->format('d/m/Y H:i')) ?></dd>
        </div>
    </dl>

    <div class="receipt-signature">
        <span></span>
        <p>Assinatura do responsável familiar</p>
    </div>

    <footer class="receipt-footer">
        <span>Documento gerado pelo sistema Cadastro Emergencial.</span>
        <span>Guarde este comprovante para conferência.</span>
    </footer>
</article>
</section>
