<?php
/** @var \Courier\Reporte\Application\ObtenerResumenDashboard\ResumenDashboardView $resumen */
/** @var string|null $rol */

require_once __DIR__ . '/../partials/view_helpers.php';

$headerActions = '';
if (in_array($rol, ['operaciones', 'bodega', 'gerente', 'admin'], true)) {
    $headerActions .= header_button('/cargas/ingreso', 'plus', 'Ingreso de Carga', 'amber');
}

$pageTitle = 'Dashboard';
$pageSubtitle = 'Resumen general de la operacion';

require __DIR__ . '/../partials/header.php';

$variacion = $resumen->variacionVsMesAnterior();
$serviceLabels = ['aereo' => 'Aereo', 'maritimo' => 'Maritimo', 'freight' => 'Freight', 'sin_especificar' => 'Sin especificar'];
$mesesCortos = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
$mesActual = $mesesCortos[date('m')];

/** Mini grafico de linea (SVG) para las tarjetas de arriba. */
$sparkline = static function (array $valores): string {
    $valores = array_values($valores);
    $n = count($valores);

    if ($n < 2) {
        $valores = [0, 0];
        $n = 2;
    }

    $max = max(max($valores), 1);
    $puntos = [];

    foreach ($valores as $i => $v) {
        $puntos[] = round($i / ($n - 1) * 100, 2) . ',' . round(28 - ($v / $max) * 24, 2);
    }

    return '<svg viewBox="0 0 100 30" preserveAspectRatio="none" class="w-24 h-8 opacity-90">'
        . '<polyline fill="none" stroke="white" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" points="' . implode(' ', $puntos) . '"/></svg>';
};

$hastaHoy = array_slice($resumen->cargasPorDia, 0, (int) date('j'), true);
$bultosHastaHoy = array_slice($resumen->bultosPorDia, 0, (int) date('j'), true);

$tarjetas = [
    [
        'titulo' => 'Cargas hoy', 'valor' => $resumen->cargasHoy, 'icono' => 'box',
        'gradiente' => 'from-blue-600 to-indigo-600',
        'detalle' => $resumen->cargasEsteMes . ' en ' . $mesActual, 'serie' => array_slice($hastaHoy, -7),
    ],
    [
        'titulo' => 'Cargas este mes', 'valor' => $resumen->cargasEsteMes, 'icono' => 'chart',
        'gradiente' => 'from-emerald-500 to-green-600',
        'detalle' => $variacion === null
            ? $resumen->cargasMesAnterior . ' el mes anterior'
            : ($variacion >= 0 ? '▲ ' : '▼ ') . number_format(abs($variacion), 1) . '% vs mes anterior',
        'serie' => $hastaHoy,
    ],
    [
        'titulo' => 'Bultos en bodega', 'valor' => $resumen->bultosEnBodega(), 'icono' => 'box',
        'gradiente' => 'from-orange-500 to-amber-500',
        'detalle' => ($resumen->bultosPorEstado['receiving']['total'] ?? 0) . ' en recepcion', 'serie' => $bultosHastaHoy,
    ],
    [
        'titulo' => 'Cargas en proceso', 'valor' => $resumen->pendientes, 'icono' => 'clock',
        'gradiente' => 'from-sky-500 to-blue-600',
        'detalle' => 'En bodega, transito o aduana', 'serie' => array_values($resumen->cargasPorMes),
    ],
    [
        'titulo' => 'Clientes con carga', 'valor' => $resumen->clientesActivos, 'icono' => 'users',
        'gradiente' => 'from-pink-500 to-rose-600',
        'detalle' => $resumen->clientesPorAprobar . ' por aprobar', 'serie' => array_values($resumen->cargasPorMes),
    ],
    [
        'titulo' => 'Salidas este mes', 'valor' => $resumen->salidas['confirmadasEsteMes'], 'icono' => 'truck',
        'gradiente' => 'from-teal-500 to-cyan-600',
        'detalle' => $resumen->salidas['pendientes'] . ' orden(es) pendiente(s)', 'serie' => [],
    ],
];

$coloresEstado = ['#2563eb', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4', '#ec4899', '#64748b'];
$totalCargasEstado = array_sum(array_column($resumen->cargasPorEstado, 'total'));
$totalServicio = array_sum($resumen->porServicioEsteMes);
$estiloServicio = [
    'aereo' => ['icono' => 'globe', 'color' => 'sky'],
    'maritimo' => ['icono' => 'truck', 'color' => 'blue'],
    'freight' => ['icono' => 'box', 'color' => 'orange'],
    'sin_especificar' => ['icono' => 'filter', 'color' => 'slate'],
];
$estiloBulto = [
    'receiving' => ['icono' => 'clock', 'color' => 'amber'],
    'available' => ['icono' => 'check', 'color' => 'emerald'],
    'in_repack' => ['icono' => 'box', 'color' => 'indigo'],
    'reserved' => ['icono' => 'lock', 'color' => 'blue'],
    'quarantine' => ['icono' => 'shield', 'color' => 'red'],
    'consumed' => ['icono' => 'truck', 'color' => 'slate'],
];
$coloresEstadoCarga = [
    'ingresado' => 'blue', 'en_bodega' => 'amber', 'en_transito' => 'indigo', 'en_aduana' => 'violet',
    'liberado' => 'teal', 'listo_entrega' => 'emerald', 'entregado' => 'slate', 'retenido' => 'red',
];
$formatoFecha = static fn (string $fecha): string => (new DateTimeImmutable($fecha))->format('d/m/Y');
?>

<!-- Tarjetas de indicadores -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-4 mb-6">
    <?php foreach ($tarjetas as $t): ?>
        <div class="relative overflow-hidden rounded-2xl p-4 text-white shadow-lg bg-gradient-to-br <?= $t['gradiente'] ?>">
            <div class="absolute -right-6 -bottom-8 w-28 h-28 rounded-full bg-white/10"></div>
            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-white/90"><?= htmlspecialchars($t['titulo']) ?></p>
                <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center"><?= icon_small($t['icono'], 'w-6 h-6') ?></span>
            </div>
            <div class="flex items-end justify-between gap-2 mt-1 relative">
                <p class="text-3xl font-bold"><?= number_format($t['valor']) ?></p>
                <?= $sparkline($t['serie']) ?>
            </div>
            <p class="text-xs text-white/85 mt-2 relative truncate" title="<?= htmlspecialchars($t['detalle']) ?>"><?= htmlspecialchars($t['detalle']) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<!-- Fila 2: ingresos del mes, cargas por estado, servicio -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-[#0b1b4d]">Ingresos del mes <span class="text-xs font-normal text-zinc-500">(<?= $mesActual . ' ' . date('Y') ?>)</span></h2>
        </div>
        <div class="h-64"><canvas id="chart-ingresos"></canvas></div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-semibold text-[#0b1b4d] mb-3">Cargas por estado</h2>
        <?php if ($totalCargasEstado === 0): ?>
            <p class="text-sm text-zinc-500">Todavia no hay cargas registradas.</p>
        <?php else: ?>
            <div class="flex items-center gap-4">
                <div class="relative w-44 h-44 shrink-0">
                    <canvas id="chart-estados"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-2xl font-bold text-[#0b1b4d]"><?= $totalCargasEstado ?></span>
                        <span class="text-[11px] text-zinc-500">cargas</span>
                    </div>
                </div>
                <ul class="flex-1 space-y-2.5">
                    <?php $i = 0; foreach ($resumen->cargasPorEstado as $grupo): ?>
                        <li class="flex items-center justify-between text-sm gap-2">
                            <span class="flex items-center gap-2 text-zinc-700">
                                <span class="w-2.5 h-2.5 rounded-full" style="background: <?= $coloresEstado[$i++ % count($coloresEstado)] ?>"></span>
                                <?= htmlspecialchars($grupo['label']) ?>
                            </span>
                            <span class="font-semibold text-black"><?= round($grupo['total'] / $totalCargasEstado * 100) ?>%</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-semibold text-[#0b1b4d] mb-3">Por servicio <span class="text-xs font-normal text-zinc-500">(este mes)</span></h2>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($resumen->porServicioEsteMes as $tipo => $cantidad): ?>
                <?php $estilo = $estiloServicio[$tipo] ?? ['icono' => 'box', 'color' => 'slate']; ?>
                <li class="flex items-center justify-between py-2.5 text-sm">
                    <span class="flex items-center gap-3 text-zinc-700">
                        <span class="w-8 h-8 rounded-lg bg-<?= $estilo['color'] ?>-100 text-<?= $estilo['color'] ?>-600 flex items-center justify-center"><?= icon_small($estilo['icono']) ?></span>
                        <?= htmlspecialchars($serviceLabels[$tipo] ?? $tipo) ?>
                    </span>
                    <span class="flex items-center gap-6">
                        <span class="font-semibold text-black"><?= $cantidad ?></span>
                        <span class="w-10 text-right text-zinc-500"><?= $totalServicio > 0 ? round($cantidad / $totalServicio * 100) : 0 ?>%</span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="flex items-center justify-between mt-2 pt-3 border-t border-slate-200 text-sm">
            <span class="font-semibold text-black">Total del mes</span>
            <span class="text-xl font-bold text-emerald-600"><?= $totalServicio ?></span>
        </div>
    </div>
</div>

<!-- Fila 3: top clientes, inventario, cargas sin acta -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-col">
        <h2 class="font-semibold text-[#0b1b4d] mb-3">Top 5 clientes</h2>
        <?php if (empty($resumen->topClientes)): ?>
            <p class="text-sm text-zinc-500">Sin cargas registradas todavia.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase bg-slate-50">
                        <th class="py-2 px-2 w-8">#</th>
                        <th class="py-2 px-2">Cliente</th>
                        <th class="py-2 px-2 text-right">Cargas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumen->topClientes as $i => $cliente): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-2 text-zinc-500"><?= $i + 1 ?></td>
                            <td class="py-2 px-2 text-black"><?= htmlspecialchars($cliente['nombre']) ?></td>
                            <td class="py-2 px-2 text-right font-semibold text-black"><?= $cliente['totalCargas'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <a href="/clientes" class="mt-auto pt-3 text-center text-sm text-blue-600 hover:underline">Ver todos los clientes &rarr;</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-col">
        <h2 class="font-semibold text-[#0b1b4d] mb-3">Inventario de bultos</h2>
        <ul class="space-y-2.5">
            <?php foreach ($resumen->bultosPorEstado as $estado => $grupo): ?>
                <?php $estilo = $estiloBulto[$estado] ?? ['icono' => 'box', 'color' => 'slate']; ?>
                <li class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-3 text-zinc-700">
                        <span class="w-7 h-7 rounded-lg bg-<?= $estilo['color'] ?>-500 text-white flex items-center justify-center"><?= icon_small($estilo['icono']) ?></span>
                        <?= htmlspecialchars($grupo['label']) ?>
                    </span>
                    <span class="font-semibold text-black"><?= number_format($grupo['total']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-200 text-sm">
            <span class="font-semibold text-black">En bodega</span>
            <span class="text-xl font-bold text-blue-600"><?= number_format($resumen->bultosEnBodega()) ?></span>
        </div>
        <a href="/inventario" class="mt-auto pt-3 text-center text-sm text-blue-600 hover:underline">Ver inventario &rarr;</a>
    </div>

    <div id="sin-acta" class="rounded-2xl shadow-sm p-5 flex flex-col bg-gradient-to-b from-red-50 to-white border border-red-100">
        <h2 class="font-semibold text-red-600 mb-3 flex items-center gap-2">
            <?= icon_small('receipt', 'w-5 h-5') ?> Cargas sin acta de recepcion
            <span class="text-xs font-normal text-red-500">(<?= $resumen->cargasSinActa['total'] ?>)</span>
        </h2>
        <?php if ($resumen->cargasSinActa['items'] === []): ?>
            <p class="text-sm text-zinc-500">Todas las cargas tienen su acta de recepcion.</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase">
                        <th class="py-2 pr-2">Tracking</th>
                        <th class="py-2 pr-2">Cliente</th>
                        <th class="py-2 text-right">Ingreso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumen->cargasSinActa['items'] as $carga): ?>
                        <tr class="border-t border-red-100">
                            <td class="py-2 pr-2">
                                <a href="/cargas/<?= htmlspecialchars($carga['id']) ?>/acta-recepcion" class="font-medium text-blue-600 hover:underline"><?= htmlspecialchars($carga['trackingNumero']) ?></a>
                            </td>
                            <td class="py-2 pr-2 text-zinc-700 truncate max-w-[9rem]"><?= htmlspecialchars($carga['clienteNombre']) ?></td>
                            <td class="py-2 text-right">
                                <span class="px-2 py-0.5 rounded-full bg-red-500 text-white text-xs"><?= htmlspecialchars($formatoFecha($carga['fecha'])) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <a href="/recepciones" class="mt-auto pt-3 text-center text-sm text-red-600 hover:underline">Ir a Recepcion de Carga &rarr;</a>
    </div>
</div>

<!-- Fila 4: tendencia mensual, cargas recientes, bultos por ubicacion -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-semibold text-[#0b1b4d] mb-3">Cargas por mes <span class="text-xs font-normal text-zinc-500">(ultimos 12 meses)</span></h2>
        <div class="h-56"><canvas id="chart-meses"></canvas></div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-[#0b1b4d]">Cargas recientes</h2>
            <a href="/cargas/ingreso" class="text-sm text-blue-600 hover:underline">Ver todas &rarr;</a>
        </div>
        <?php if (empty($resumen->cargasRecientes)): ?>
            <p class="text-sm text-zinc-500">Todavia no hay cargas registradas.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach (array_slice($resumen->cargasRecientes, 0, 5) as $carga): ?>
                    <?php $color = $coloresEstadoCarga[$carga['estado']] ?? 'slate'; ?>
                    <li class="py-2.5 flex items-center gap-3 text-sm">
                        <span class="w-8 h-8 rounded-lg bg-<?= $color ?>-500 text-white flex items-center justify-center shrink-0"><?= icon_small('box') ?></span>
                        <div class="min-w-0 flex-1">
                            <a href="/cargas/<?= htmlspecialchars($carga['id']) ?>" class="block font-medium text-black hover:text-blue-600 truncate"><?= htmlspecialchars($carga['trackingNumero']) ?></a>
                            <p class="text-xs text-zinc-500 truncate"><?= htmlspecialchars($carga['clienteNombre']) ?></p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="px-2 py-0.5 rounded-full bg-<?= $color ?>-100 text-<?= $color ?>-700 text-xs font-medium"><?= htmlspecialchars($carga['estadoLabel']) ?></span>
                            <p class="text-xs text-zinc-500 mt-0.5"><?= htmlspecialchars($formatoFecha($carga['fecha'])) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-semibold text-[#0b1b4d] mb-3">Bultos por ubicacion</h2>
        <?php if ($resumen->bultosPorUbicacion === []): ?>
            <p class="text-sm text-zinc-500">No hay bultos en bodega.</p>
        <?php else: ?>
            <div class="h-56"><canvas id="chart-ubicaciones"></canvas></div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif';
    Chart.defaults.color = '#64748b';

    var datos = <?= json_encode([
        'dias' => array_map('strval', array_keys($resumen->cargasPorDia)),
        'cargasPorDia' => array_values($resumen->cargasPorDia),
        'bultosPorDia' => array_values($resumen->bultosPorDia),
        'estados' => array_values(array_column($resumen->cargasPorEstado, 'label')),
        'estadosTotal' => array_values(array_column($resumen->cargasPorEstado, 'total')),
        'coloresEstado' => $coloresEstado,
        'meses' => array_map(static fn (string $ym) => $mesesCortos[substr($ym, 5, 2)] . ' ' . substr($ym, 2, 2), array_keys($resumen->cargasPorMes)),
        'cargasPorMes' => array_values($resumen->cargasPorMes),
        'ubicaciones' => array_column($resumen->bultosPorUbicacion, 'nombre'),
        'ubicacionesTotal' => array_column($resumen->bultosPorUbicacion, 'total'),
    ], JSON_UNESCAPED_UNICODE) ?>;

    var enteros = { ticks: { precision: 0 }, beginAtZero: true, grid: { color: '#eef2f7' } };

    var ctxIngresos = document.getElementById('chart-ingresos').getContext('2d');
    var degradado = ctxIngresos.createLinearGradient(0, 0, 0, 240);
    degradado.addColorStop(0, 'rgba(37, 99, 235, 0.30)');
    degradado.addColorStop(1, 'rgba(37, 99, 235, 0)');

    new Chart(ctxIngresos, {
        type: 'line',
        data: {
            labels: datos.dias,
            datasets: [
                { label: 'Cargas', data: datos.cargasPorDia, borderColor: '#2563eb', backgroundColor: degradado, fill: true, tension: 0.35, pointRadius: 2 },
                { label: 'Bultos', data: datos.bultosPorDia, borderColor: '#16a34a', backgroundColor: '#16a34a', tension: 0.35, pointRadius: 2 }
            ]
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10, usePointStyle: true } } },
            scales: { y: enteros, x: { grid: { display: false } } }
        }
    });

    var estados = document.getElementById('chart-estados');
    if (estados) {
        new Chart(estados, {
            type: 'doughnut',
            data: { labels: datos.estados, datasets: [{ data: datos.estadosTotal, backgroundColor: datos.coloresEstado, borderWidth: 2 }] },
            options: { cutout: '62%', plugins: { legend: { display: false } } }
        });
    }

    var ctxMeses = document.getElementById('chart-meses').getContext('2d');
    var verde = ctxMeses.createLinearGradient(0, 0, 0, 220);
    verde.addColorStop(0, '#22c55e');
    verde.addColorStop(1, '#15803d');

    new Chart(ctxMeses, {
        type: 'bar',
        data: { labels: datos.meses, datasets: [{ label: 'Cargas', data: datos.cargasPorMes, backgroundColor: verde, borderRadius: 4, maxBarThickness: 26 }] },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: enteros, x: { grid: { display: false } } }
        }
    });

    var ubicaciones = document.getElementById('chart-ubicaciones');
    if (ubicaciones) {
        new Chart(ubicaciones, {
            type: 'bar',
            data: {
                labels: datos.ubicaciones,
                datasets: [{ label: 'Bultos', data: datos.ubicacionesTotal, backgroundColor: ['#2563eb', '#16a34a', '#f97316', '#8b5cf6', '#ef4444'], borderRadius: 4, maxBarThickness: 22 }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: enteros, y: { grid: { display: false } } }
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
