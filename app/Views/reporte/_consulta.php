<?php
/**
 * Consulta de Reportes: filtrar cargas por estado (en bodega, en salida, salieron), cliente y
 * aduanero; ver el detalle por carga o totales por cliente, aduanero u OS/contenedor; y bajar
 * exactamente lo mismo en Excel. Una fila es una carga en un estado: si parte de la carga esta en
 * bodega y parte en un contenedor, sale en dos filas.
 *
 * @var \Courier\Reporte\Application\ObtenerReporteOperativo\ReporteOperativoView $reporte
 * @var array{filas: array<int, array<string, mixed>>, totales: array<string, int|float>} $consulta
 * @var string $estadoConsulta
 * @var string $verConsulta
 */
use App\Controllers\ReporteController;

$nf = static fn (float $v, int $d = 0): string => number_format($v, $d, '.', ',');
$fecha = static fn (?string $ymd): string => $ymd ? substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2) . '/' . substr($ymd, 0, 4) : '—';
$t = $consulta['totales'];
$campo = 'rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm';
$estadoBadge = static fn (array $f): string => match ($f['estado']) {
    'bodega' => badge('En bodega', 'emerald'),
    'salida' => badge($f['esOs'] ? 'En OS' : 'En contenedor', $f['esOs'] ? 'sky' : 'blue'),
    default => badge('Salio', 'violet'),
};
$excelUrl = '/reportes/consulta/excel?' . http_build_query(array_filter([
    'estado' => $estadoConsulta, 'ver' => $verConsulta, 'cliente' => $filtroCliente, 'aduanero' => $filtroAduanero,
    'desde' => $reporte->desde, 'hasta' => $reporte->hasta,
], static fn ($v) => $v !== '' && $v !== null));
$th = 'py-2 pr-3';
$thn = 'py-2 pr-3 text-right';
$tdn = 'py-2 pr-3 text-right whitespace-nowrap';
?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <?= card_header('filter', 'Consulta de cargas', 'amber') ?>
    <form method="get" action="/reportes" id="reporte-filtros" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-3 items-end">
        <input type="hidden" name="alerta" id="alerta-input" value="<?= htmlspecialchars($alertaActiva) ?>">
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Estado</label>
            <select name="estado" class="w-full <?= $campo ?>">
                <?php foreach (ReporteController::ESTADOS_CONSULTA as $valor => $texto): ?>
                    <option value="<?= $valor ?>" <?= $estadoConsulta === $valor ? 'selected' : '' ?>><?= htmlspecialchars($texto) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="xl:col-span-2">
            <label class="block text-sm font-medium text-zinc-700 mb-1">Cliente</label>
            <select name="cliente" class="w-full <?= $campo ?>">
                <option value="">Todos</option>
                <?php foreach ($clientes as $cl): ?>
                    <option value="<?= htmlspecialchars($cl->id()) ?>" <?= $filtroCliente === $cl->id() ? 'selected' : '' ?>><?= htmlspecialchars($cl->nombreCompleto()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Aduanero</label>
            <select name="aduanero" class="w-full <?= $campo ?>">
                <option value="">Todos</option>
                <?php foreach ($aduaneros as $a): ?>
                    <option value="<?= htmlspecialchars($a->identificador()) ?>" <?= $filtroAduanero === $a->identificador() ? 'selected' : '' ?>><?= htmlspecialchars($a->nombre()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1" title="Aplica a lo que salio">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($reporte->desde) ?>" class="w-full <?= $campo ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1" title="Aplica a lo que salio">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($reporte->hasta) ?>" class="w-full <?= $campo ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Ver</label>
            <select name="ver" class="w-full <?= $campo ?>">
                <?php foreach (ReporteController::VISTAS_CONSULTA as $valor => $texto): ?>
                    <option value="<?= $valor ?>" <?= $verConsulta === $valor ? 'selected' : '' ?>><?= htmlspecialchars($texto) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sm:col-span-2 lg:col-span-4 xl:col-span-7 flex flex-wrap items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-5 py-2 text-sm font-medium hover:bg-amber-400 transition">
                <?= icon_small('search') ?><span>Buscar</span>
            </button>
            <a href="<?= htmlspecialchars($excelUrl) ?>" class="inline-flex items-center gap-2 bg-emerald-600 text-white rounded-lg px-5 py-2 text-sm font-medium hover:bg-emerald-500 transition">
                <?= icon_small('download') ?><span>Descargar Excel</span>
            </a>
            <a href="/reportes" class="text-sm text-zinc-500 hover:text-black">Limpiar</a>
            <span class="text-xs text-zinc-500">Las fechas aplican a lo que <strong>salio</strong>; lo que esta en bodega o en salida es lo de hoy.</span>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6" id="consulta-resultado" data-vivo-region="global">
    <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm mb-4">
        <span><strong class="text-black text-lg"><?= $nf($t['cargas']) ?></strong> cargas</span>
        <span><strong class="text-black text-lg"><?= $nf($t['bultos']) ?></strong> bultos</span>
        <?php if ($estadoConsulta === 'todos'): ?>
            <span class="text-emerald-700"><?= $nf($t['bodega']) ?> en bodega</span>
            <span class="text-blue-700"><?= $nf($t['salida']) ?> en salida</span>
            <span class="text-violet-700"><?= $nf($t['salieron']) ?> salieron</span>
        <?php endif; ?>
        <span><strong class="text-black"><?= $nf($t['kg'], 1) ?></strong> kg</span>
        <span><strong class="text-black"><?= $nf($t['p3'], 2) ?></strong> p3</span>
        <span><strong class="text-black"><?= $nf($t['valor'], 2) ?></strong> valor</span>
    </div>

    <?php if ($consulta['filas'] === []): ?>
        <p class="text-sm text-zinc-500 py-4">No hay cargas con estos filtros.</p>
    <?php elseif ($verConsulta === 'detalle'): ?>
        <div class="overflow-x-auto max-h-[36rem] overflow-y-auto border border-slate-200 rounded-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-slate-50">
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 px-3">Tracking</th>
                        <th class="<?= $th ?>">Cliente</th>
                        <th class="<?= $th ?>">Aduanero</th>
                        <th class="<?= $th ?>">Estado</th>
                        <th class="<?= $th ?>">OS / DO</th>
                        <th class="<?= $th ?>">Contenedor</th>
                        <th class="<?= $thn ?>">Bultos</th>
                        <th class="<?= $thn ?>">Kg</th>
                        <th class="<?= $thn ?>">P3</th>
                        <th class="<?= $thn ?>">Valor</th>
                        <th class="<?= $th ?>">Ingreso</th>
                        <th class="<?= $th ?>">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consulta['filas'] as $f): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-2 px-3 font-mono whitespace-nowrap"><a href="/cargas/<?= htmlspecialchars($f['cargaId']) ?>" class="text-amber-600 hover:underline"><?= htmlspecialchars($f['tracking']) ?></a></td>
                            <td class="<?= $th ?> text-zinc-700"><?= htmlspecialchars($f['cliente']) ?></td>
                            <td class="<?= $th ?> text-zinc-700"><?= htmlspecialchars($f['aduanero']) ?></td>
                            <td class="<?= $th ?> whitespace-nowrap"><?= $estadoBadge($f) ?></td>
                            <td class="<?= $th ?> whitespace-nowrap"><?= $f['ordenId'] ? '<a href="/salidas/' . htmlspecialchars($f['ordenId']) . '" class="text-amber-600 hover:underline">' . htmlspecialchars($f['orden']) . '</a>' : '—' ?></td>
                            <td class="<?= $th ?> font-mono text-zinc-700 whitespace-nowrap"><?= htmlspecialchars($f['contenedor'] ?? '—') ?></td>
                            <td class="<?= $tdn ?> text-black"><?= $nf($f['bultos']) ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $nf($f['kg'], 1) ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $nf($f['p3'], 2) ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $nf($f['valor'], 2) ?></td>
                            <td class="<?= $th ?> text-zinc-500 whitespace-nowrap"><?= $fecha($f['ingreso']) ?></td>
                            <td class="<?= $th ?> text-zinc-500 whitespace-nowrap" title="<?= $f['estado'] === 'bodega' ? 'Entro a bodega' : ($f['estado'] === 'salida' ? 'Se armo la orden' : 'Se confirmo la salida') ?>">
                                <?= $fecha($f['fecha']) ?><?php if ($f['dias'] !== null): ?> <span class="<?= $f['dias'] > 30 ? 'text-red-600 font-medium' : '' ?>">(<?= $f['dias'] ?> d)</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="sticky bottom-0 bg-slate-100 font-semibold text-black">
                    <tr>
                        <td class="py-2 px-3" colspan="6">Totales</td>
                        <td class="<?= $tdn ?>"><?= $nf($t['bultos']) ?></td>
                        <td class="<?= $tdn ?>"><?= $nf($t['kg'], 1) ?></td>
                        <td class="<?= $tdn ?>"><?= $nf($t['p3'], 2) ?></td>
                        <td class="<?= $tdn ?>"><?= $nf($t['valor'], 2) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: $titulo = ['cliente' => 'Cliente', 'aduanero' => 'Aduanero', 'contenedor' => 'OS / DO'][$verConsulta]; ?>
        <div class="overflow-x-auto max-h-[36rem] overflow-y-auto border border-slate-200 rounded-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-slate-50">
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 px-3"><?= $titulo ?></th>
                        <?php if ($verConsulta === 'contenedor'): ?><th class="<?= $th ?>">Contenedor</th><th class="<?= $th ?>">Naviera</th><?php endif; ?>
                        <th class="<?= $thn ?>">Cargas</th>
                        <th class="<?= $thn ?>">En bodega</th>
                        <th class="<?= $thn ?>">En salida</th>
                        <th class="<?= $thn ?>">Salieron</th>
                        <th class="<?= $thn ?>">Total bultos</th>
                        <th class="<?= $thn ?>">Kg</th>
                        <th class="<?= $thn ?>">P3</th>
                        <th class="<?= $thn ?>">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consulta['filas'] as $g): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-2 px-3 text-black font-medium">
                                <?= $g['ordenId'] ? '<a href="/salidas/' . htmlspecialchars($g['ordenId']) . '" class="text-amber-600 hover:underline">' . htmlspecialchars($g['nombre']) . '</a>' : htmlspecialchars($g['nombre']) ?>
                            </td>
                            <?php if ($verConsulta === 'contenedor'): ?>
                                <td class="<?= $th ?> font-mono text-zinc-700 whitespace-nowrap"><?= htmlspecialchars($g['contenedor'] ?? '—') ?></td>
                                <td class="<?= $th ?> text-zinc-700"><?= htmlspecialchars($g['naviera'] ?? '—') ?></td>
                            <?php endif; ?>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $g['cargas'] ?></td>
                            <td class="<?= $tdn ?> text-emerald-700"><?= $g['bodega'] ? $nf($g['bodega']) : '—' ?></td>
                            <td class="<?= $tdn ?> text-blue-700"><?= $g['salida'] ? $nf($g['salida']) : '—' ?></td>
                            <td class="<?= $tdn ?> text-violet-700"><?= $g['salieron'] ? $nf($g['salieron']) : '—' ?></td>
                            <td class="<?= $tdn ?> text-black font-medium"><?= $nf($g['bultos']) ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $nf($g['kg'], 1) ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $nf($g['p3'], 2) ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $nf($g['valor'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="sticky bottom-0 bg-slate-100 font-semibold text-black">
                    <tr>
                        <td class="py-2 px-3" <?= $verConsulta === 'contenedor' ? 'colspan="3"' : '' ?>>Totales (<?= count($consulta['filas']) ?>)</td>
                        <td class="<?= $tdn ?>"><?= $nf($t['cargas']) ?></td>
                        <td class="<?= $tdn ?>"><?= $nf($t['bodega']) ?></td>
                        <td class="<?= $tdn ?>"><?= $nf($t['salida']) ?></td>
                        <td class="<?= $tdn ?>"><?= $nf($t['salieron']) ?></td>
                        <td class="<?= $tdn ?>"><?= $nf($t['bultos']) ?></td>
                        <td class="<?= $tdn ?>"><?= $nf($t['kg'], 1) ?></td>
                        <td class="<?= $tdn ?>"><?= $nf($t['p3'], 2) ?></td>
                        <td class="<?= $tdn ?>"><?= $nf($t['valor'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
