<?php $activeDeliveryPage = $activeDeliveryPage ?? 'historico'; ?>
<nav class="delivery-section-nav" aria-label="Navegacao de entregas">
    <a class="<?= $activeDeliveryPage === 'historico' ? 'is-active' : '' ?>" href="<?= h(url('/gestor/entregas')) ?>">Historico</a>
    <a class="<?= $activeDeliveryPage === 'validacao' ? 'is-active' : '' ?>" href="<?= h(url('/gestor/entregas/validacao')) ?>">Validar QR</a>
    <a class="<?= $activeDeliveryPage === 'lote' ? 'is-active' : '' ?>" href="<?= h(url('/gestor/entregas/lote')) ?>">Entrega em lote</a>
</nav>
