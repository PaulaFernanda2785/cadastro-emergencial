<section class="dashboard-header no-print receipt-preview-header">
    <div>
        <span class="eyebrow">Comprovante de cadastro familiar</span>
        <h1>Pré-visualização do ticket</h1>
        <p><?= h($familia['responsavel_nome']) ?> - residência <?= h($familia['protocolo']) ?></p>
    </div>
</section>

<section class="receipt-preview-shell">
<section class="receipt-actions no-print">
    <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'])) ?>">Voltar para residência</a>
    <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'])) ?>">Ver família</a>
    <button
        type="button"
        class="secondary-button"
        data-family-receipt-share
    >Enviar para WhatsApp</button>
    <form method="post" action="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'] . '/comprovante/email')) ?>" class="inline-form js-prevent-double-submit">
        <?= csrf_field() ?>
        <?= idempotency_field('cadastro.familia.receipt.email.' . (int) $familia['id']) ?>
        <button type="submit" class="secondary-button" data-loading-text="Enviando...">Enviar por e-mail</button>
    </form>
    <button type="button" class="primary-button" data-receipt-print>Imprimir ticket</button>
    <span class="receipt-share-status" data-family-receipt-share-status></span>
</section>

<?php
$camposPendentes = familia_campos_pendentes($familia);
$whatsappTargetType = (string) ($whatsappTarget['tipo'] ?? '');
$whatsappTargetLabel = $whatsappTargetType === 'representante' ? 'representante familiar' : 'responsavel familiar';
?>

<article
    class="receipt-ticket family-receipt-ticket"
    data-family-receipt-ticket
    data-receipt-code="<?= h($receiptCode) ?>"
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
        <span>Comprovante de Cadastro Familiar</span>
    </header>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Código</dt>
            <dd><?= h($receiptCode) ?></dd>
        </div>
        <div>
            <dt>Cadastro</dt>
            <dd><?= h(date('d/m/Y H:i', strtotime((string) ($familia['criado_em'] ?? 'now')))) ?></dd>
        </div>
        <div>
            <dt>Protocolo</dt>
            <dd><?= h($familia['protocolo']) ?></dd>
        </div>
        <div>
            <dt>Município</dt>
            <dd><?= h($familia['municipio_nome']) ?>/<?= h($familia['uf']) ?></dd>
        </div>
        <div>
            <dt>Localidade</dt>
            <dd><?= h($familia['localidade']) ?></dd>
        </div>
        <div>
            <dt>Evento</dt>
            <dd><?= h($familia['tipo_evento']) ?></dd>
        </div>
    </dl>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Responsável</dt>
            <dd><?= h($familia['responsavel_nome']) ?></dd>
        </div>
        <div>
            <dt>CPF</dt>
            <dd><?= h($familia['responsavel_cpf'] ?: '-') ?></dd>
        </div>
        <div>
            <dt>RG</dt>
            <dd><?= h($familia['responsavel_rg'] ?: '-') ?></dd>
        </div>
        <div>
            <dt>Integrantes</dt>
            <dd><?= h($familia['quantidade_integrantes']) ?></dd>
        </div>
        <div>
            <dt>Situação</dt>
            <dd><?= h(familia_situacao_label($familia['situacao_familia'] ?? null)) ?></dd>
        </div>
        <div>
            <dt>Renda</dt>
            <dd><?= h(familia_renda_label($familia['renda_familiar'] ?? null)) ?></dd>
        </div>
    </dl>

    <?php if (!empty($familia['representante_nome'])): ?>
        <div class="receipt-separator"></div>
        <dl class="receipt-lines">
            <div>
                <dt>Represent.</dt>
                <dd><?= h($familia['representante_nome']) ?></dd>
            </div>
            <div>
                <dt>CPF rep.</dt>
                <dd><?= h($familia['representante_cpf'] ?: '-') ?></dd>
            </div>
        </dl>
    <?php endif; ?>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Endereço</dt>
            <dd>
                <?= h($familia['endereco']) ?>
                <?php if (!empty($familia['complemento'])): ?>
                    - <?= h($familia['complemento']) ?>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt>Bairro</dt>
            <dd><?= h($familia['bairro_comunidade']) ?></dd>
        </div>
        <div>
            <dt>Imóvel</dt>
            <dd><?= h(residencia_imovel_label($familia['imovel'] ?? null)) ?></dd>
        </div>
        <div>
            <dt>Condição</dt>
            <dd><?= h(residencia_condicao_label($familia['condicao_residencia'] ?? null)) ?></dd>
        </div>
    </dl>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines receipt-pending-lines">
        <div>
            <dt>Pendentes</dt>
            <dd><?= h($camposPendentes === [] ? 'Nenhum campo pendente' : familia_campos_pendentes_resumo($familia, 6)) ?></dd>
        </div>
    </dl>

    <div class="receipt-separator"></div>

    <div class="receipt-qr">
        <canvas data-family-receipt-qr data-qr-value="<?= h($validationUrl) ?>" aria-label="QR Code de validação do cadastro familiar"></canvas>
        <strong><?= h($receiptCode) ?></strong>
        <span>Leia este QR na pagina Registrar para registrar os itens antes da entrega.</span>
    </div>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Emitido</dt>
            <dd><?= h($generatedAt->format('d/m/Y H:i')) ?></dd>
        </div>
    </dl>

    <footer class="receipt-footer">
        <span>Documento gerado pelo sistema Cadastro Emergencial.</span>
        <span>Apresente este comprovante para registro e conferencia da ajuda humanitaria.</span>
    </footer>
</article>
</section>
