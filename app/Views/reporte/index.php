<?php
/** @var \Courier\Reporte\Application\ObtenerReporteOperativo\ReporteOperativoView $reporte */
$pageTitle = 'Reportes';
$pageSubtitle = 'Operacion de bodega, despachos y facturacion por periodo';
require __DIR__ . '/../partials/header.php';

$serviceLabels = ['aereo' => 'Aereo', 'maritimo' => 'Maritimo', 'freight' => 'Freight', 'sin_especificar' => 'Sin especificar'];
$totalCargasPeriodo = array_sum($reporte->cargasPorServicio);

$inventarioLabels = [
    'receiving' => 'En recepcion',
    'available' => 'Disponible',
    'in_repack' => 'En reempaque',
    'reserved' => 'Reservado',
    'quarantine' => 'En cuarentena',
    'consumed' => 'Consumido',
];

$inventarioColores = [
    'receiving' => 'amber',
    'available' => 'emerald',
    'in_repack' => 'indigo',
    'reserved' => 'blue',
    'quarantine' => 'red',
    'consumed' => 'slate',
];
?>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <?= card_header('filter', 'Periodo y filtros', 'amber') ?>
    <form method="get" action="/reportes" class="flex items-end gap-4 flex-wrap" id="reporte-filtros">
        <input type="hidden" name="etapa" id="flujo-etapa-input" value="<?= htmlspecialchars($etapaActiva) ?>">
        <input type="hidden" name="resumen" id="resumen-input" value="<?= htmlspecialchars($resumenActivo) ?>">
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($reporte->desde) ?>"
                   class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($reporte->hasta) ?>"
                   class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Aduanero</label>
            <select name="aduanero" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
                <option value="">Todos</option>
                <?php foreach ($aduaneros as $a): ?>
                    <option value="<?= htmlspecialchars($a->identificador()) ?>" <?= $filtroAduanero === $a->identificador() ? 'selected' : '' ?>><?= htmlspecialchars($a->nombre()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Cliente</label>
            <select name="cliente" class="max-w-[16rem] rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
                <option value="">Todos</option>
                <?php foreach ($clientes as $cl): ?>
                    <option value="<?= htmlspecialchars($cl->id()) ?>" <?= $filtroCliente === $cl->id() ? 'selected' : '' ?>><?= htmlspecialchars($cl->nombreCompleto()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
            <?= icon_small('filter') ?><span>Aplicar</span>
        </button>
    </form>
    <p class="text-xs text-zinc-500 mt-2">El periodo aplica a "Salieron" y a los indicadores de abajo. Aduanero y cliente filtran el Flujo de cargas.</p>
</div>

<?php require __DIR__ . '/_flujo_cargas.php'; ?>
<?php require __DIR__ . '/_resumenes.php'; ?>

<script>
// Flujo de cargas: las tarjetas cambian la lista visible; la etapa se recuerda en la URL
// (para el Excel y al aplicar filtros) y se vuelve a aplicar cuando "En vivo" refresca la zona.
(function () {
    var etapa = document.getElementById('flujo-etapa-input').value;

    function aplicar() {
        document.querySelectorAll('[data-flujo-panel]').forEach(function (p) {
            p.classList.toggle('hidden', p.getAttribute('data-flujo-panel') !== etapa);
        });
        document.querySelectorAll('[data-flujo-etapa]').forEach(function (t) {
            var color = t.getAttribute('data-color');
            var activa = t.getAttribute('data-flujo-etapa') === etapa;
            t.classList.toggle('ring-2', activa);
            t.classList.toggle('ring-' + color + '-400', activa);
            t.classList.toggle('bg-' + color + '-50', activa);
            t.classList.toggle('bg-white', !activa);
        });
        document.getElementById('flujo-etapa-input').value = etapa;
        var params = new URLSearchParams(new FormData(document.getElementById('reporte-filtros')));
        var excel = document.getElementById('flujo-excel');
        if (excel) {
            excel.href = '/reportes/flujo/excel?' + params.toString();
        }
        var url = new URL(window.location.href);
        url.searchParams.set('etapa', etapa);
        history.replaceState(null, '', url);
    }

    document.addEventListener('click', function (e) {
        var tarjeta = e.target.closest('[data-flujo-etapa]');
        if (tarjeta) {
            etapa = tarjeta.getAttribute('data-flujo-etapa');
            aplicar();
        }
    });
    document.addEventListener('vivo:actualizado', aplicar);

    // Resumen de salidas: pestanas (cliente / aduanero / contenedor) y su Excel con los mismos filtros.
    function aplicarResumen() {
        var activo = document.getElementById('resumen-input').value;
        document.querySelectorAll('[data-resumen-panel]').forEach(function (p) {
            p.classList.toggle('hidden', p.getAttribute('data-resumen-panel') !== activo);
        });
        document.querySelectorAll('[data-resumen-tab]').forEach(function (t) {
            var esta = t.getAttribute('data-resumen-tab') === activo;
            t.classList.toggle('bg-white', esta);
            t.classList.toggle('shadow', esta);
            t.classList.toggle('text-zinc-900', esta);
            t.classList.toggle('text-zinc-500', !esta);
        });
        var params = new URLSearchParams(new FormData(document.getElementById('reporte-filtros')));
        var excel = document.getElementById('resumen-excel');
        if (excel) {
            excel.href = '/reportes/resumen/excel?' + params.toString();
        }
        var url = new URL(window.location.href);
        url.searchParams.set('resumen', activo);
        history.replaceState(null, '', url);
    }

    document.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-resumen-tab]');
        if (tab) {
            document.getElementById('resumen-input').value = tab.getAttribute('data-resumen-tab');
            aplicarResumen();
        }
    });
    document.addEventListener('vivo:actualizado', aplicarResumen);
    aplicarResumen();
    aplicar();
})();
</script>

<?php require __DIR__ . '/_operacion_bodega.php'; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border-l-4 border-emerald-500 p-4">
        <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Total cobrado</p>
        <p class="text-2xl font-bold text-emerald-600 mt-1"><?= number_format($reporte->totalCobrado(), 2) ?></p>
        <p class="text-xs text-zinc-500 mt-1 flex items-center gap-1"><?= icon_small('check', 'w-3.5 h-3.5') ?> <?= $reporte->facturacion['pagada']['cantidad'] ?? 0 ?> factura(s) pagada(s)</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border-l-4 border-amber-500 p-4">
        <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Total pendiente de cobro</p>
        <p class="text-2xl font-bold text-amber-600 mt-1"><?= number_format($reporte->totalPendiente(), 2) ?></p>
        <p class="text-xs text-zinc-500 mt-1 flex items-center gap-1"><?= icon_small('clock', 'w-3.5 h-3.5') ?> <?= $reporte->facturacion['pendiente']['cantidad'] ?? 0 ?> factura(s) pendiente(s)</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border-l-4 border-red-500 p-4">
        <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Total anulado</p>
        <p class="text-2xl font-bold text-red-600 mt-1"><?= number_format($reporte->totalAnulado(), 2) ?></p>
        <p class="text-xs text-zinc-500 mt-1 flex items-center gap-1"><?= icon_small('x', 'w-3.5 h-3.5') ?> <?= $reporte->facturacion['anulada']['cantidad'] ?? 0 ?> factura(s) anulada(s)</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="font-semibold text-black mb-4 flex items-center gap-2"><?= icon_small('box', 'w-4 h-4 text-amber-600') ?> Cargas por servicio en el periodo</h2>
        <?php if ($totalCargasPeriodo === 0): ?>
            <p class="text-sm text-zinc-500">Sin cargas registradas en este periodo.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($reporte->cargasPorServicio as $tipo => $cantidad): ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-zinc-600"><?= $serviceLabels[$tipo] ?? $tipo ?></span>
                            <span class="font-medium text-black"><?= $cantidad ?></span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="bg-amber-500 h-2 rounded-full" style="width: <?= $totalCargasPeriodo > 0 ? round($cantidad / $totalCargasPeriodo * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="font-semibold text-black mb-4 flex items-center gap-2"><?= icon_small('users', 'w-4 h-4 text-amber-600') ?> Top clientes por facturacion</h2>
        <?php if (empty($reporte->topClientesFacturacion)): ?>
            <p class="text-sm text-zinc-500">Sin facturacion en este periodo.</p>
        <?php else: ?>
            <ol class="space-y-3">
                <?php foreach ($reporte->topClientesFacturacion as $i => $cliente): ?>
                    <?php $rankColors = [1 => 'amber', 2 => 'slate', 3 => 'orange']; $rankColor = $rankColors[$i + 1] ?? 'slate'; ?>
                    <li class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-<?= $rankColor ?>-500 text-white text-xs font-bold flex items-center justify-center"><?= $i + 1 ?></span>
                            <span class="text-sm font-medium text-black"><?= htmlspecialchars($cliente['nombre']) ?></span>
                        </div>
                        <span class="text-sm text-zinc-700"><?= number_format($cliente['totalFacturado'], 2) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="font-semibold text-black mb-4 flex items-center gap-2"><?= icon_small('box', 'w-4 h-4 text-zinc-600') ?> Inventario actual por estado</h2>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                <th class="py-2 pr-4">Estado</th>
                <th class="py-2">Cantidad de LPNs</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reporte->inventarioPorEstado as $estado => $cantidad): ?>
                <tr class="border-b border-slate-200">
                    <td class="py-2 pr-4"><?= badge($inventarioLabels[$estado] ?? $estado, $inventarioColores[$estado] ?? 'slate') ?></td>
                    <td class="py-2 text-black font-medium"><?= $cantidad ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
