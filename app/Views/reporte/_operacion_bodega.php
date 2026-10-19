<?php
/** @var \Courier\Reporte\Application\ObtenerReporteOperativo\ReporteOperativoView $reporte */
$ob = $reporte->operacionBodega;
$n = static fn ($valor): string => number_format((float) $valor, 0, '.', ',');
$dinero = static fn ($valor): string => '$' . number_format((float) $valor, 2, '.', ',');
$kpi = static function (string $titulo, string $valor, string $detalle, string $color): string {
    return '<div class="bg-white rounded-xl shadow-sm border-l-4 border-' . $color . '-500 p-4">'
        . '<p class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">' . htmlspecialchars($titulo) . '</p>'
        . '<p class="text-2xl font-bold text-black mt-1">' . $valor . '</p>'
        . '<p class="text-xs text-zinc-500 mt-1">' . $detalle . '</p></div>';
};
$rangos = ['0-30' => ['0 a 30 dias', 'emerald'], '31-60' => ['31 a 60 dias', 'amber'], '61-90' => ['61 a 90 dias', 'orange'], '+90' => ['Mas de 90 dias', 'red']];
$bultosInventario = max(1, (int) $ob['inventario']['bultos']);
?>

<h2 class="text-lg font-semibold text-black mb-3 flex items-center gap-2"><?= icon_small('box', 'w-5 h-5 text-amber-600') ?> Operacion de bodega</h2>

<p class="text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-2">Movimiento del periodo</p>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?= $kpi('Cargas ingresadas', $n($ob['ingresos']['cargas']), $n($ob['ingresos']['bultos']) . ' bultos · ' . $n($ob['ingresos']['peso']) . ' kg · ' . $dinero($ob['ingresos']['valor']), 'blue') ?>
    <?= $kpi('Recepciones completas', $n($ob['recepciones']['cargas']), $ob['recepciones']['horasPromedio'] !== null ? 'Promedio ' . $ob['recepciones']['horasPromedio'] . ' h desde el registro hasta el ultimo escaneo' : 'Cargas con todos sus bultos escaneados', 'lime') ?>
    <?= $kpi('Salidas despachadas', $n($ob['despachos']['salidas']), $n($ob['despachos']['contenedores']) . ' contenedor(es)', 'orange') ?>
    <?= $kpi('Bultos despachados', $n($ob['despachos']['bultos']), 'En salidas confirmadas del periodo', 'indigo') ?>
</div>

<p class="text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-2">Situacion actual</p>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <?= $kpi('En bodega', $n($ob['inventario']['bultos']) . ' bultos', $n($ob['inventario']['cargas']) . ' cargas de ' . $n($ob['inventario']['clientes']) . ' clientes', 'emerald') ?>
    <?= $kpi('Valor en bodega', $dinero($ob['inventario']['valor']), $n($ob['inventario']['peso']) . ' kg', 'emerald') ?>
    <?= $kpi('Por escanear', $n($ob['porEscanear']['bultos']) . ' bultos', 'Etiquetas impresas que bodega no ha confirmado (' . $n($ob['porEscanear']['cargas']) . ' cargas)', 'amber') ?>
    <?= $kpi('Sin etiquetas', $n($ob['sinEtiquetas']['bultos']) . ' bultos', $n($ob['sinEtiquetas']['cargas']) . ' cargas sin LPNs: no se pueden despachar', 'red') ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-semibold text-black mb-4">Antiguedad del inventario</h3>
        <div class="space-y-3">
            <?php foreach ($rangos as $clave => [$etiqueta, $color]): $r = $ob['antiguedad'][$clave]; $pct = (int) round($r['bultos'] * 100 / $bultosInventario); ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-zinc-700"><?= $etiqueta ?></span>
                        <span class="text-black font-medium"><?= $n($r['bultos']) ?> bultos · <?= $n($r['cargas']) ?> cargas</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-2.5 bg-<?= $color ?>-500" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-zinc-500 mt-4">Segun la fecha de ingreso. Lo de mas de 60 dias conviene cobrarlo como almacenaje o avisar al cliente.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-semibold text-black mb-4">Clientes con mas bultos en bodega</h3>
        <?php if ($ob['topClientes'] === []): ?>
            <p class="text-sm text-zinc-500">No hay mercancia en bodega.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                            <th class="py-2 pr-4">Cliente</th>
                            <th class="py-2 pr-4 text-right">Cargas</th>
                            <th class="py-2 pr-4 text-right">Bultos</th>
                            <th class="py-2 text-right">Mas antigua</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ob['topClientes'] as $c): ?>
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-4 text-black"><?= htmlspecialchars($c['nombre']) ?></td>
                                <td class="py-2 pr-4 text-right"><?= $n($c['cargas']) ?></td>
                                <td class="py-2 pr-4 text-right font-medium"><?= $n($c['bultos']) ?></td>
                                <td class="py-2 text-right <?= $c['diasMax'] > 60 ? 'text-red-600 font-medium' : 'text-zinc-600' ?>"><?= $n($c['diasMax']) ?> dias</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-8">
    <h3 class="font-semibold text-black mb-1">Recepciones incompletas (mas de 24 h)</h3>
    <p class="text-xs text-zinc-500 mb-4">Cargas con etiquetas impresas hace mas de un dia que bodega aun no termina de escanear: revisar faltantes.</p>
    <?php if ($ob['incompletas'] === []): ?>
        <p class="text-sm text-emerald-700">Todo al dia: no hay recepciones atrasadas.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 pr-4">Tracking</th>
                        <th class="py-2 pr-4">Cliente</th>
                        <th class="py-2 pr-4 text-right">Escaneados</th>
                        <th class="py-2 text-right">Esperando</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ob['incompletas'] as $c): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-4"><a href="/cargas/<?= htmlspecialchars($c['cargaId']) ?>/recepcion" class="font-mono text-blue-700 hover:underline"><?= htmlspecialchars($c['tracking']) ?></a></td>
                            <td class="py-2 pr-4 text-black"><?= htmlspecialchars($c['cliente']) ?></td>
                            <td class="py-2 pr-4 text-right"><?= $c['recibidos'] ?> / <?= $c['total'] ?></td>
                            <td class="py-2 text-right text-red-600 font-medium"><?= $c['horas'] >= 48 ? $n(intdiv($c['horas'], 24)) . ' dias' : $c['horas'] . ' h' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<h2 class="text-lg font-semibold text-black mb-3 flex items-center gap-2"><?= icon_small('receipt', 'w-5 h-5 text-zinc-600') ?> Facturacion y servicios</h2>
