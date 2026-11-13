<?php
use App\Libraries\Csrf;
/** @var \Courier\Carga\Application\ConsultarTrackingPorNumero\TrackingView|null $view */
/** @var array<string, mixed>|null $rastreo detalle interno (solo inventario/trafico/gerencia/owner) */
$pageTitle = 'Rastreo de carga';
$pageSubtitle = 'Consulte el estado de una carga por su numero de tracking';
$numero = $numero ?? '';
$interno = $interno ?? false;
$rastreo = $rastreo ?? null;
require __DIR__ . '/../partials/header.php';

$h = static fn ($v): string => htmlspecialchars((string) $v);
$fecha = static function (?string $v, bool $hora = false): string {
    if ($v === null || $v === '') {
        return '—';
    }
    $t = strtotime($v);

    return $t ? date($hora ? 'd/m/Y H:i' : 'd/m/Y', $t) : $v;
};
$num = static fn ($v, int $d = 2): string => number_format((float) $v, $d);

$bultoEstadoClases = [
    'receiving' => 'bg-amber-500/10 text-amber-600',
    'available' => 'bg-emerald-500/10 text-emerald-600',
    'in_repack' => 'bg-indigo-500/10 text-indigo-600',
    'reserved' => 'bg-blue-500/10 text-blue-600',
    'quarantine' => 'bg-red-500/10 text-red-600',
    'consumed' => 'bg-slate-500/10 text-slate-600',
];
$bultoEstadoLabel = [
    'receiving' => 'Por recibir',
    'available' => 'En bodega',
    'in_repack' => 'En reempaque',
    'reserved' => 'En orden de salida',
    'quarantine' => 'En cuarentena',
    'consumed' => 'Despachado / consumido',
];
/** Estado de una OS/DO para mostrar. */
$ordenEstado = static function (string $estado, bool $esOs): array {
    return match ($estado) {
        'pendiente' => $esOs ? ['OS pendiente (sin DO)', 'bg-amber-500/10 text-amber-700'] : ['DO pendiente (cargando)', 'bg-blue-500/10 text-blue-700'],
        'confirmada' => ['Salio (confirmada)', 'bg-emerald-500/10 text-emerald-700'],
        default => ['Cancelada', 'bg-slate-500/10 text-slate-500'],
    };
};
?>

<form method="post" action="/tracking/buscar" class="bg-white rounded-xl shadow p-6 max-w-xl flex gap-3 mb-6">
    <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
    <input type="text" name="tracking_numero" placeholder="Ej: LAN-2026-A1B2C3" required value="<?= $h($numero) ?>"
           class="flex-1 rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
    <button type="submit" class="bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
        Buscar
    </button>
</form>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-xl">
        <?= $h($error) ?>
    </div>
<?php endif; ?>

<?php if ($view): ?>
    <div class="bg-white rounded-xl shadow p-6 <?= $interno ? 'max-w-6xl' : 'max-w-2xl' ?> space-y-4">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <p class="text-2xl font-bold text-black tracking-tight"><?= $h($view->trackingNumero) ?></p>
                <?php if ($rastreo !== null): ?>
                    <p class="text-sm text-zinc-600"><?= $h($rastreo['carga']['cliente']) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($interno): ?>
                <a href="/cargas/<?= $h($view->cargaId) ?>" class="text-sm rounded-lg border border-zinc-300 px-3 py-1.5 text-zinc-700 hover:bg-slate-50">Ver carga completa →</a>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <span class="px-3 py-1 rounded-full bg-amber-500/10 text-amber-600 text-sm font-medium">
                <?= $h($view->estadoActualLabel) ?>
            </span>
            <?php if ($view->tipoServicio !== null): ?>
                <span class="text-sm text-zinc-500">Servicio: <?= $h(ucfirst($view->tipoServicio)) ?></span>
            <?php endif; ?>
            <?php foreach ($view->bultosPorEstado as $grupo): ?>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?= $bultoEstadoClases[$grupo['estado']] ?? 'bg-slate-500/10 text-slate-600' ?>">
                    <?= $h($grupo['cantidad'] . ' ' . ($interno ? ($bultoEstadoLabel[$grupo['estado']] ?? $grupo['estadoLabel']) : $grupo['estadoLabel'])) ?>
                </span>
            <?php endforeach; ?>
        </div>

        <?php if ($rastreo !== null): ?>
            <?php
            // Donde esta: bultos agrupados por la OS/DO vigente en que van, y los que siguen sueltos en bodega.
            $donde = [];
            $sinOrden = ['bodega' => 0, 'otros' => []];
            foreach ($rastreo['bultos'] as $b) {
                if ($b['ordenId'] !== null) {
                    $donde[$b['ordenId']] ??= ['orden' => $b['orden'], 'estado' => $b['ordenEstado'], 'bultos' => 0, 'escaneados' => 0];
                    $donde[$b['ordenId']]['bultos']++;
                    $donde[$b['ordenId']]['escaneados'] += $b['escaneado'] ? 1 : 0;
                } elseif ($b['estado'] === 'available') {
                    $sinOrden['bodega']++;
                } else {
                    $label = $bultoEstadoLabel[$b['estado']] ?? $b['estado'];
                    $sinOrden['otros'][$label] = ($sinOrden['otros'][$label] ?? 0) + 1;
                }
            }
            $ordenesPorId = array_column($rastreo['ordenes'], null, 'id');
            ?>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <p class="text-xs font-semibold uppercase text-blue-800 mb-2">Donde esta ahora</p>
                <ul class="space-y-1.5 text-sm text-black">
                    <?php foreach ($donde as $ordenId => $d): ?>
                        <?php $o = $ordenesPorId[$ordenId] ?? null; [$lbl, $cls] = $ordenEstado((string) $d['estado'], $o['esOs'] ?? false); ?>
                        <li class="flex items-center gap-2 flex-wrap">
                            <strong><?= $d['bultos'] ?> bulto(s)</strong> en
                            <a href="/salidas/<?= $h($ordenId) ?>" class="font-semibold text-blue-700 underline"><?= $h($d['orden']) ?></a>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $cls ?>"><?= $h($lbl) ?></span>
                            <?php if ($o !== null && $o['contenedor'] !== null): ?>
                                <span class="text-zinc-600">· contenedor <?= $h($o['contenedor']) ?></span>
                            <?php endif; ?>
                            <span class="<?= $d['escaneados'] < $d['bultos'] ? 'text-red-600' : 'text-emerald-700' ?>">
                                · <?= $d['escaneados'] ?>/<?= $d['bultos'] ?> escaneados
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($sinOrden['bodega'] > 0): ?>
                        <li><strong><?= $sinOrden['bodega'] ?> bulto(s)</strong> en bodega, sin orden de salida</li>
                    <?php endif; ?>
                    <?php foreach ($sinOrden['otros'] as $label => $n): ?>
                        <li><strong><?= $n ?> bulto(s)</strong>: <?= $h($label) ?></li>
                    <?php endforeach; ?>
                    <?php if ($rastreo['bultos'] === []): ?>
                        <li class="text-zinc-600">La carga aun no tiene etiquetas (LPN) generadas.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-sm pt-2">
                <?php foreach ([
                    'Cliente' => $rastreo['carga']['cliente'],
                    'Aduanero' => $rastreo['carga']['aduanero'],
                    'Proveedor' => $rastreo['carga']['proveedor'],
                    'Ingreso' => $fecha($rastreo['carga']['fechaIngreso'], true),
                    'Registrado por' => $rastreo['carga']['registradoPor'],
                    'Contenedor de llegada' => $rastreo['carga']['contenedor'],
                ] as $k => $v): ?>
                    <div>
                        <dt class="text-xs text-zinc-500 uppercase"><?= $h($k) ?></dt>
                        <dd class="text-black font-medium break-words"><?= $h(($v ?? '') !== '' ? $v : '—') ?></dd>
                    </div>
                <?php endforeach; ?>
                <?php if ($rastreo['carga']['notas'] !== null): ?>
                    <div class="col-span-2">
                        <dt class="text-xs text-zinc-500 uppercase">Notas</dt>
                        <dd class="text-black"><?= $h($rastreo['carga']['notas']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>

        <?php if ($view->fechaEstimadaLlegada): ?>
            <p class="text-sm text-zinc-700">
                <strong>Fecha estimada de llegada:</strong> <?= $h($view->fechaEstimadaLlegada) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($view->lineas)): ?>
            <h2 class="font-semibold text-black pt-4 border-t border-slate-200">Productos a entregar</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                            <th class="py-2 pr-3">Descripcion</th>
                            <?php if ($interno): ?><th class="py-2 pr-3">Marca</th><?php endif; ?>
                            <th class="py-2 pr-3">UoM</th>
                            <th class="py-2 pr-3 text-right">Cantidad</th>
                            <?php if ($interno): ?><th class="py-2 pr-3 text-right">Cub. p3</th><?php endif; ?>
                            <th class="py-2 pr-3 text-right">Peso (kg)</th>
                            <?php if ($interno): ?><th class="py-2 text-right">Valor</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $tot = ['cantidad' => 0, 'p3' => 0, 'peso' => 0, 'valor' => 0]; ?>
                        <?php foreach ($view->lineas as $linea): ?>
                            <?php
                            $tot['cantidad'] += (float) $linea['cantidad'];
                            $tot['p3'] += (float) $linea['cubicajePies'];
                            $tot['peso'] += (float) $linea['peso'];
                            $tot['valor'] += (float) $linea['valor'];
                            ?>
                            <tr class="border-b border-slate-200">
                                <td class="py-2 pr-3 text-black font-medium"><?= $h($linea['descripcion']) ?></td>
                                <?php if ($interno): ?><td class="py-2 pr-3 text-zinc-700"><?= $h($linea['marca'] ?? '') ?></td><?php endif; ?>
                                <td class="py-2 pr-3 text-zinc-700"><?= $h($linea['unidadMedida']) ?></td>
                                <td class="py-2 pr-3 text-zinc-700 text-right"><?= $h((string) $linea['cantidad']) ?></td>
                                <?php if ($interno): ?><td class="py-2 pr-3 text-zinc-700 text-right"><?= $num($linea['cubicajePies']) ?></td><?php endif; ?>
                                <td class="py-2 pr-3 text-zinc-700 text-right"><?= $h((string) $linea['peso']) ?></td>
                                <?php if ($interno): ?><td class="py-2 text-zinc-700 text-right"><?= $num($linea['valor']) ?></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if ($interno && count($view->lineas) > 1): ?>
                        <tfoot>
                            <tr class="font-semibold text-black">
                                <td class="py-2 pr-3" colspan="3">Total</td>
                                <td class="py-2 pr-3 text-right"><?= $num($tot['cantidad'], 0) ?></td>
                                <td class="py-2 pr-3 text-right"><?= $num($tot['p3']) ?></td>
                                <td class="py-2 pr-3 text-right"><?= $num($tot['peso']) ?></td>
                                <td class="py-2 text-right"><?= $num($tot['valor']) ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($rastreo !== null): ?>
            <h2 class="font-semibold text-black pt-4 border-t border-slate-200">Ordenes de salida (OS / DO)</h2>
            <?php if ($rastreo['ordenes'] === []): ?>
                <p class="text-sm text-zinc-500">Esta carga no ha estado en ninguna orden de salida.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                                <th class="py-2 pr-3">OS / DO</th>
                                <th class="py-2 pr-3">Estado</th>
                                <th class="py-2 pr-3">Contenedor</th>
                                <th class="py-2 pr-3">Naviera / barco</th>
                                <th class="py-2 pr-3">Cargue / ETD</th>
                                <th class="py-2 pr-3">Destino</th>
                                <th class="py-2 pr-3 text-right">Bultos</th>
                                <th class="py-2 text-right">Escaneados</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rastreo['ordenes'] as $o): ?>
                                <?php [$lbl, $cls] = $ordenEstado($o['estado'], $o['esOs']); ?>
                                <tr class="border-b border-slate-200 align-top <?= $o['estado'] === 'cancelada' ? 'text-zinc-400' : '' ?>">
                                    <td class="py-2 pr-3">
                                        <?php if ($o['estado'] !== 'cancelada'): ?>
                                            <a href="/salidas/<?= $h($o['id']) ?>" class="font-semibold text-blue-700 underline"><?= $h($o['etiqueta']) ?></a>
                                        <?php else: ?>
                                            <span class="font-semibold"><?= $h($o['etiqueta']) ?></span>
                                        <?php endif; ?>
                                        <div class="text-xs text-zinc-500">Creada <?= $h($fecha($o['creada'], true)) ?></div>
                                    </td>
                                    <td class="py-2 pr-3">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $cls ?>"><?= $h($lbl) ?></span>
                                        <?php if ($o['confirmada'] !== null): ?>
                                            <div class="text-xs text-zinc-500"><?= $h($fecha($o['confirmada'], true)) ?></div>
                                        <?php endif; ?>
                                        <?php if ($o['nota'] !== null): ?>
                                            <div class="text-xs text-zinc-500"><?= $h($o['nota']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 pr-3"><?= $h($o['contenedor'] ?? '—') ?><?= $o['sello'] !== null ? '<div class="text-xs text-zinc-500">Sello ' . $h($o['sello']) . '</div>' : '' ?></td>
                                    <td class="py-2 pr-3"><?= $h($o['naviera'] ?? '—') ?><?= $o['barco'] !== null ? '<div class="text-xs text-zinc-500">' . $h($o['barco']) . '</div>' : '' ?></td>
                                    <td class="py-2 pr-3"><?= $h($fecha($o['fechaCargue'])) ?><div class="text-xs text-zinc-500">ETD <?= $h($fecha($o['etd'])) ?></div></td>
                                    <td class="py-2 pr-3"><?= $h($o['destino'] ?? '—') ?></td>
                                    <td class="py-2 pr-3 text-right"><?= $o['bultos'] ?></td>
                                    <td class="py-2 text-right <?= $o['estado'] !== 'cancelada' && $o['escaneados'] < $o['bultos'] ? 'text-red-600 font-semibold' : '' ?>"><?= $o['escaneados'] ?>/<?= $o['bultos'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($rastreo['bultos'] !== []): ?>
                <details class="pt-4 border-t border-slate-200" <?= count($rastreo['bultos']) <= 20 ? 'open' : '' ?>>
                    <summary class="font-semibold text-black cursor-pointer select-none">Bultos (<?= count($rastreo['bultos']) ?> etiquetas)</summary>
                    <div class="overflow-x-auto mt-2">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                                    <th class="py-2 pr-3">Etiqueta</th>
                                    <th class="py-2 pr-3">Estado</th>
                                    <th class="py-2 pr-3">Ubicacion</th>
                                    <th class="py-2 pr-3">Caja reempaque</th>
                                    <th class="py-2 pr-3">OS / DO</th>
                                    <th class="py-2">Escaneado salida</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rastreo['bultos'] as $b): ?>
                                    <tr class="border-b border-slate-100">
                                        <td class="py-1.5 pr-3 font-mono text-black"><?= $h($b['codigo']) ?></td>
                                        <td class="py-1.5 pr-3">
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $bultoEstadoClases[$b['estado']] ?? '' ?>"><?= $h($bultoEstadoLabel[$b['estado']] ?? $b['estado']) ?></span>
                                        </td>
                                        <td class="py-1.5 pr-3 text-zinc-700"><?= $h($b['ubicacion'] ?? '—') ?></td>
                                        <td class="py-1.5 pr-3 text-zinc-700 font-mono"><?= $h($b['caja'] ?? '—') ?></td>
                                        <td class="py-1.5 pr-3">
                                            <?php if ($b['ordenId'] !== null): ?>
                                                <a href="/salidas/<?= $h($b['ordenId']) ?>" class="text-blue-700 underline"><?= $h($b['orden']) ?></a>
                                            <?php else: ?>
                                                <span class="text-zinc-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-1.5">
                                            <?php if ($b['escaneado'] === null): ?>
                                                <span class="text-zinc-400">—</span>
                                            <?php elseif ($b['escaneado']): ?>
                                                <span class="text-emerald-700 font-medium">Si</span>
                                            <?php else: ?>
                                                <span class="text-red-600 font-medium">No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
        <?php endif; ?>

        <h2 class="font-semibold text-black pt-4 border-t border-slate-200">Historial</h2>
        <ol class="space-y-3">
            <?php foreach ($view->historial as $entry): ?>
                <li class="flex items-start gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full bg-amber-400"></span>
                    <div>
                        <p class="text-sm font-medium text-black"><?= $h($entry['estadoLabel']) ?></p>
                        <p class="text-xs text-zinc-500"><?= $h($fecha($entry['fecha'], true)) ?></p>
                        <?php if ($interno && !empty($entry['comentario'])): ?>
                            <p class="text-xs text-zinc-600"><?= $h($entry['comentario']) ?></p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
