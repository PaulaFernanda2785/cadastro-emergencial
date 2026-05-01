<?php require BASE_PATH . '/resources/views/gestor/entregas/_nav.php'; ?>

<section class="dashboard-header deliveries-header">
    <div>
        <span class="eyebrow">Validacao de cadastro familiar</span>
        <h1>Validar comprovante por QR Code</h1>
        <p>Leia o QR do comprovante da familia para abrir o cadastro validado e registrar a entrega.</p>
    </div>
</section>

<section class="delivery-qr-panel no-print" data-delivery-qr-scanner data-validate-base="<?= h(url('/gestor/entregas/validar')) ?>">
    <div class="delivery-qr-heading">
        <div>
            <span class="eyebrow">Leitura por camera</span>
            <h2>Comprovante de cadastro familiar</h2>
        </div>
        <div class="delivery-qr-actions">
            <button type="button" class="primary-button" data-delivery-qr-start>Ler QR Code</button>
            <button type="button" class="secondary-button" data-delivery-qr-stop hidden>Parar leitura</button>
        </div>
    </div>

    <video class="delivery-qr-video" data-delivery-qr-video muted playsinline hidden></video>
    <p class="delivery-qr-status" data-delivery-qr-status>Leia o QR Code impresso no comprovante ou informe o codigo manualmente.</p>

    <form method="get" action="<?= h(url('/gestor/entregas/validar')) ?>" class="delivery-qr-manual-form">
        <label class="field">
            <span>Codigo do comprovante</span>
            <input type="text" name="codigo" placeholder="FAM-000000-XXXXXXXXXX" maxlength="26" autocomplete="off">
        </label>
        <button type="submit" class="secondary-button">Validar codigo</button>
    </form>
</section>
