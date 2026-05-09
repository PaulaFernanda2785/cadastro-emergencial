<?php
$validationMode = ($validationMode ?? 'entregar') === 'registrar' ? 'registrar' : 'entregar';
$isRegistration = $validationMode === 'registrar';
$validateUrl = $isRegistration ? '/gestor/entregas/registrar/validar' : '/gestor/entregas/validar';
$titleText = $isRegistration ? 'Registrar itens por QR Code' : 'Validar QR para entrega';
$description = $isRegistration
    ? 'Leia o QR do comprovante da familia para abrir o cadastro e registrar os itens previstos.'
    : 'Leia o QR do comprovante da familia para confirmar a entrega dos itens ja registrados.';

require BASE_PATH . '/resources/views/gestor/entregas/_nav.php';
?>

<section class="dashboard-header deliveries-header">
    <div>
        <span class="eyebrow"><?= $isRegistration ? 'Registro de itens' : 'Confirmacao de entrega' ?></span>
        <h1><?= h($titleText) ?></h1>
        <p><?= h($description) ?></p>
    </div>
</section>

<section class="delivery-qr-panel no-print" data-delivery-qr-scanner data-validate-base="<?= h(url($validateUrl)) ?>">
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

    <form method="get" action="<?= h(url($validateUrl)) ?>" class="delivery-qr-manual-form">
        <label class="field">
            <span>Codigo do comprovante</span>
            <input type="text" name="codigo" placeholder="FAM-000000-XXXXXXXXXX" maxlength="26" autocomplete="off">
        </label>
        <button type="submit" class="secondary-button"><?= $isRegistration ? 'Registrar por codigo' : 'Validar codigo' ?></button>
    </form>
</section>
