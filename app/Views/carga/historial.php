<?php
/** @var array<int, \Courier\Aduanero\Domain\Aduanero> $aduaneros */
/** @var array<int, \Courier\Carga\Domain\Carga> $cargas */
/** @var array<string, string> $nombresClientes */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalPages */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Historial de cargas';
$pageSubtitle = 'Cargas que ya estan en una salida (DO) o despachadas';
$headerActions = '<a href="/cargas/excel?vista=salida" id="btn-excel-cargas" title="Descargar en Excel las cargas con los filtros actuales"'
    . ' class="inline-flex items-center gap-2 bg-emerald-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-emerald-500 transition">'
    . icon_small('download') . '<span>Excel</span></a>';
require __DIR__ . '/../partials/header.php';
?>

<?php // Lo que sigue en bodega esta en Ingreso de Carga; si sacan una carga del DO, vuelve alla. ?>
<?php $vistasListado = ['salida' => 'En salida', 'todas' => 'Todas']; require __DIR__ . '/_listado.php'; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
