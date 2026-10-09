<?php
/** @var \Courier\Lpn\Domain\EtiquetaLpnConfig $etiquetaConfig */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Etiqueta LPN';
$pageSubtitle = 'Datos que se imprimen en las etiquetas de bultos';
$backHref = '/configuracion';
require __DIR__ . '/../partials/header.php';
?>

<div class="bg-white rounded-xl shadow-sm p-6 max-w-md">
    <?php $volver = '/configuracion/etiqueta-lpn'; require __DIR__ . '/../lpn/_plantilla_etiqueta.php'; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
