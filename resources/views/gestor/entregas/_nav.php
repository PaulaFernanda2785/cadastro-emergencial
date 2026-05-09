<?php $activeDeliveryPage = $activeDeliveryPage ?? 'historico'; ?>
<nav class="delivery-section-nav" aria-label="Navegacao de entregas">
    <a class="<?= $activeDeliveryPage === 'historico' ? 'is-active' : '' ?>" href="<?= h(url('/gestor/entregas')) ?>">Historico</a>
    <a class="<?= $activeDeliveryPage === 'registrar' ? 'is-active' : '' ?>" href="<?= h(url('/gestor/entregas/registrar')) ?>">Registrar</a>
    <a class="<?= $activeDeliveryPage === 'validacao' ? 'is-active' : '' ?>" href="<?= h(url('/gestor/entregas/validacao')) ?>">Validar QR</a>
    <a class="<?= $activeDeliveryPage === 'lote' ? 'is-active' : '' ?>" href="<?= h(url('/gestor/entregas/lote')) ?>">Entregar em lote</a>
</nav>
