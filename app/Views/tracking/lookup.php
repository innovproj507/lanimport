<?php
use App\Libraries\Csrf;
/** @var \Courier\Carga\Application\ConsultarTrackingPorNumero\TrackingView|null $view */
/** @var array<string, mixed>|null $rastreo detalle interno (solo inventario/trafico/gerencia/owner) */
$pageTitle = 'Rastreo de carga';
$pageSubtitle = 'Consulte el estado de una carga por su numero de tracking';
$numero = $numero ?? '';
$interno = $interno ?? false;
$rastreo = $rastreo ?? null;
$movimientos = $movimientos ?? null;
$eliminada = $eliminada ?? null;
$caja = $caja ?? null;
$coincidencias = $coincidencias ?? null;
$listado = $listado ?? null;
if ($interno) {
    $pageSubtitle = 'Busque por tracking, etiqueta, o por nombre de cliente o aduanero';
}
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
    'in_transfer' => 'bg-indigo-500/10 text-indigo-700',
    'returned' => 'bg-orange-500/10 text-orange-700',
    'in_return' => 'bg-orange-500/10 text-orange-700',
];
$bultoEstadoLabel = [
    'receiving' => 'Por recibir',
    'available' => 'En bodega',
    'in_repack' => 'En reempaque',
    'reserved' => 'En orden de salida',
    'quarantine' => 'En cuarentena',
    'consumed' => 'Despachado / consumido',
    'in_transfer' => 'En traslado a otra bodega',
    'returned' => 'Devuelto al proveedor',
    'in_return' => 'En orden de devolucion al proveedor (aun en bodega)',
];
/** Estado de una OS/DO para mostrar. */
$ordenEstado = static function (string $estado, bool $esOs): array {
    return match ($estado) {
        'pendiente' => $esOs ? ['OS pendiente (sin DO)', 'bg-amber-500/10 text-amber-700'] : ['DO pendiente (cargando)', 'bg-blue-500/10 text-blue-700'],
        'confirmada' => ['Salio (confirmada)', 'bg-emerald-500/10 text-emerald-700'],
        default => ['Cancelada', 'bg-slate-500/10 text-slate-500'],
    };
};
/** Color del punto de cada movimiento en la linea de tiempo. */
$colorMovimiento = static fn (string $tipo): string => match ($tipo) {
    'estado' => 'bg-amber-400',
    'salida_agregada', 'salida_movida', 'do_generado', 'contenedor', 'salida_ajuste' => 'bg-blue-500',
    'escaneo_salida', 'recepcion_escaneo' => 'bg-cyan-500',
    'salida_confirmada' => 'bg-emerald-500',
    'devuelta_bodega', 'orden_cancelada', 'etiquetas_eliminadas', 'carga_eliminada' => 'bg-red-500',
    'reempaque', 'traslado' => 'bg-indigo-500',
    'devuelta_proveedor', 'devolucion_orden' => 'bg-orange-500',
    default => 'bg-slate-400',
};
$lineaTiempo = static function (array $eventos) use ($h, $fecha, $colorMovimiento): void {
    if ($eventos === []) {
        echo '<p class="text-sm text-zinc-500">Sin movimientos registrados.</p>';

        return;
    }
    echo '<ol class="relative border-l border-slate-200 ml-1 space-y-4">';
    foreach ($eventos as $e) {
        echo '<li class="ml-4 relative">'
            . '<span class="absolute -left-[21px] top-1.5 w-2.5 h-2.5 rounded-full ' . $colorMovimiento($e['tipo']) . '"></span>'
            . '<p class="text-sm font-medium text-black">' . $h($e['titulo']) . '</p>'
            . ($e['detalle'] !== null ? '<p class="text-sm text-zinc-700">' . $h($e['detalle']) . '</p>' : '')
            . '<p class="text-xs text-zinc-500">' . $h($fecha($e['fecha'], true)) . ($e['usuario'] !== null ? ' · ' . $h($e['usuario']) : '') . '</p>'
            . '</li>';
    }
    echo '</ol>';
};
?>

<form method="post" action="/tracking/buscar" class="bg-white rounded-xl shadow p-6 max-w-xl flex gap-3 mb-6">
    <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
    <input type="text" name="tracking_numero" placeholder="<?= $interno ? 'Tracking, LPN, cliente o aduanero' : 'Ej: LAN-2026-A1B2C3, A1B2C3 o LPN-A1B2C3-1' ?>" required value="<?= $h($numero) ?>"
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

<?php if ($eliminada !== null): ?>
    <?php if (!$interno): ?>
        <div class="bg-white rounded-xl shadow p-6 max-w-2xl">
            <p class="text-lg font-bold text-black"><?= $h($eliminada['tracking']) ?></p>
            <p class="mt-2 text-sm text-red-700">Este tracking fue anulado el <?= $h($fecha($eliminada['eliminadaAt'])) ?>. Consulte con LAN Import.</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow p-6 max-w-6xl space-y-4">
            <div class="flex items-center gap-3 flex-wrap">
                <p class="text-2xl font-bold text-zinc-400 line-through"><?= $h($eliminada['tracking']) ?></p>
                <span class="px-3 py-1 rounded-full bg-red-500/10 text-red-700 text-sm font-semibold">Carga eliminada</span>
            </div>
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-black space-y-1">
                <p><strong>Eliminada el</strong> <?= $h($fecha($eliminada['eliminadaAt'], true)) ?><?= $eliminada['eliminadaPor'] ? ' <strong>por</strong> ' . $h($eliminada['eliminadaPor']) : '' ?></p>
                <p><strong>Motivo:</strong> <?= $h($eliminada['motivo'] ?: 'No se indico motivo.') ?></p>
            </div>
            <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                <?php foreach ([
                    'Cliente' => $eliminada['cliente'],
                    'Aduanero' => $eliminada['aduanero'],
                    'Proveedor' => $eliminada['proveedor'],
                    'Ingreso' => $fecha($eliminada['fechaIngreso'], true),
                    'Registrado por' => $eliminada['registradoPor'],
                ] as $k => $v): ?>
                    <div>
                        <dt class="text-xs text-zinc-500 uppercase"><?= $h($k) ?></dt>
                        <dd class="text-black font-medium"><?= $h(($v ?? '') !== '' ? $v : '—') ?></dd>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($eliminada['notas'])): ?>
                    <div class="col-span-2"><dt class="text-xs text-zinc-500 uppercase">Notas</dt><dd class="text-black"><?= $h($eliminada['notas']) ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if ($eliminada['lineas'] !== []): ?>
                <h2 class="font-semibold text-black pt-4 border-t border-slate-200">Productos que tenia</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                                <th class="py-2 pr-3">Descripcion</th><th class="py-2 pr-3">Marca</th>
                                <th class="py-2 pr-3 text-right">Cantidad</th><th class="py-2 pr-3 text-right">Cub. p3</th>
                                <th class="py-2 pr-3 text-right">Peso (kg)</th><th class="py-2 text-right">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eliminada['lineas'] as $l): ?>
                                <tr class="border-b border-slate-200">
                                    <td class="py-2 pr-3 text-black"><?= $h($l['descripcion'] ?? '') ?></td>
                                    <td class="py-2 pr-3"><?= $h($l['marca'] ?? '') ?></td>
                                    <td class="py-2 pr-3 text-right"><?= $num($l['cantidad'] ?? 0, 0) ?></td>
                                    <td class="py-2 pr-3 text-right"><?= $num($l['cubicaje_p3'] ?? 0) ?></td>
                                    <td class="py-2 pr-3 text-right"><?= $num($l['peso'] ?? 0) ?></td>
                                    <td class="py-2 text-right"><?= $num($l['valor'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <h2 class="font-semibold text-black pt-4 border-t border-slate-200">Historial de movimientos</h2>
            <?php $lineaTiempo($eliminada['movimientos']); ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($caja !== null): ?>
    <?php
    $ordenEstadoCaja = static fn (string $e, bool $esOs): array => match ($e) {
        'pendiente' => $esOs ? ['OS pendiente', 'bg-amber-500/10 text-amber-700'] : ['DO pendiente (cargando)', 'bg-blue-500/10 text-blue-700'],
        'confirmada' => ['Salio (confirmada)', 'bg-emerald-500/10 text-emerald-700'],
        default => ['Cancelada', 'bg-slate-500/10 text-slate-500'],
    };
    $totalBultosCaja = array_sum(array_column($caja['contenido'], 'bultos'));
    ?>
    <div class="bg-white rounded-xl shadow p-6 space-y-5">
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-2xl font-bold text-black font-mono"><?= $h($caja['codigo']) ?></p>
            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-violet-500/10 text-violet-700">Caja de reempaque</span>
            <span class="px-3 py-1 rounded-full text-sm font-medium <?= $bultoEstadoClases[$caja['estado']] ?? 'bg-slate-500/10 text-slate-600' ?>"><?= $h($bultoEstadoLabel[$caja['estado']] ?? $caja['estado']) ?></span>
        </div>
        <p class="text-sm text-zinc-600">No tiene tracking propio: lleva los bultos de los trackings de abajo, que siguen siendo sus cargas.</p>

        <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-sm">
            <?php foreach ([
                'A nombre de' => trim(($caja['clienteCodigo'] ? $caja['clienteCodigo'] . ' — ' : '') . $caja['cliente']),
                'Marca' => $caja['marca'] ?? '—',
                'Contiene' => $totalBultosCaja . ' bulto(s) de ' . count($caja['contenido']) . ' carga(s)',
                'Armada' => $fecha($caja['creada'], true),
            ] + (hay_varias_bodegas() ? ['Bodega' => $caja['bodega'] ?? '—'] : []) + ['Ubicacion' => $caja['ubicacion'] ?? '—'] as $k => $v): ?>
                <div><dt class="text-xs text-zinc-500 uppercase"><?= $k ?></dt><dd class="text-black font-medium"><?= $h($v) ?></dd></div>
            <?php endforeach; ?>
            <?php if ($caja['reempaque'] !== null): ?>
                <div><dt class="text-xs text-zinc-500 uppercase">Reempaque</dt><dd><a href="/reempaques/<?= $h($caja['reempaque']['id']) ?>" class="font-semibold text-blue-700 underline"><?= $h($caja['reempaque']['codigo']) ?></a></dd></div>
            <?php endif; ?>
            <?php if ($caja['dentroDe'] !== null): ?>
                <div><dt class="text-xs text-zinc-500 uppercase">Dentro de la caja</dt><dd><a href="/tracking?numero=<?= $h(rawurlencode($caja['dentroDe'])) ?>" class="font-mono text-blue-700 underline"><?= $h($caja['dentroDe']) ?></a></dd></div>
            <?php endif; ?>
        </dl>

        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
            <p class="text-xs font-semibold uppercase text-blue-800 mb-2">Donde esta ahora</p>
            <?php if ($caja['ordenes'] === []): ?>
                <p class="text-sm text-black">En bodega, sin orden de salida.</p>
            <?php else: ?>
                <ul class="space-y-1.5 text-sm text-black">
                    <?php foreach ($caja['ordenes'] as $o): [$lbl, $cls] = $ordenEstadoCaja($o['estado'], $o['esOs']); ?>
                        <li class="flex flex-wrap items-center gap-2">
                            <a href="/salidas/<?= $h($o['id']) ?>" class="font-semibold text-blue-700 underline"><?= $h($o['etiqueta']) ?></a>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $cls ?>"><?= $h($lbl) ?></span>
                            <?php if ($o['contenedor'] !== null): ?><span class="text-zinc-600">· contenedor <?= $h($o['contenedor']) ?></span><?php endif; ?>
                            <?php if ($o['estado'] !== 'cancelada'): ?><span class="<?= $o['escaneado'] ? 'text-emerald-700' : 'text-red-600' ?>">· <?= $o['escaneado'] ? 'escaneada' : 'sin escanear' ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="overflow-x-auto">
            <p class="font-semibold text-black mb-2">Trackings que contiene</p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 pr-3">Tracking</th>
                        <th class="py-2 pr-3">Cliente de la carga</th>
                        <th class="py-2 pr-3">Compania</th>
                        <th class="py-2 pr-3">Marca</th>
                        <th class="py-2 pr-3 text-right">Bultos</th>
                        <th class="py-2">Etiquetas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($caja['contenido'] as $c): ?>
                        <tr class="border-b border-slate-100 align-top">
                            <td class="py-2 pr-3"><a href="/tracking?numero=<?= $h(rawurlencode($c['tracking'])) ?>" class="font-semibold text-blue-700 underline"><?= $h($c['tracking']) ?></a></td>
                            <td class="py-2 pr-3"><?= $h($c['cliente']) ?></td>
                            <td class="py-2 pr-3"><?= $h($c['proveedor'] !== '' ? $c['proveedor'] : '—') ?></td>
                            <td class="py-2 pr-3"><?= $h($c['marca'] !== '' ? $c['marca'] : '—') ?></td>
                            <td class="py-2 pr-3 text-right"><?= $c['bultos'] ?></td>
                            <td class="py-2 font-mono text-xs text-zinc-600"><?= $h($c['etiquetas']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($caja['contenido'] === []): ?>
                        <tr><td colspan="6" class="py-3 text-zinc-500">La caja todavia no tiene bultos (el reempaque sigue abierto).</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($coincidencias !== null): ?>
    <div class="bg-white rounded-xl shadow p-6 max-w-3xl space-y-5">
        <p class="text-sm text-zinc-600">Varias coincidencias para "<strong><?= $h($numero) ?></strong>". Elija una:</p>
        <?php foreach (['clientes' => ['Clientes', 'cliente'], 'aduaneros' => ['Aduaneros', 'aduanero']] as $clave => [$titulo, $param]): ?>
            <?php if ($coincidencias[$clave] !== []): ?>
                <div>
                    <h2 class="font-semibold text-black mb-2"><?= $titulo ?></h2>
                    <ul class="divide-y divide-slate-100 border border-slate-200 rounded-lg">
                        <?php foreach ($coincidencias[$clave] as $c): ?>
                            <li>
                                <a href="/tracking?<?= $param ?>=<?= $h(rawurlencode($c['id'])) ?>&amp;numero=<?= $h(rawurlencode($numero)) ?>"
                                   class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-amber-50">
                                    <span class="text-black font-medium"><?= $h($c['nombre']) ?>
                                        <?php if (!empty($c['codigo'])): ?><span class="text-xs text-zinc-500 font-normal"><?= $h($c['codigo']) ?></span><?php endif; ?>
                                        <?php if ($param === 'aduanero'): ?><span class="text-xs text-zinc-500 font-normal"><?= $h($c['id']) ?></span><?php endif; ?>
                                    </span>
                                    <span class="text-sm text-zinc-500"><?= $c['cargas'] ?> carga(s) &rarr;</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($listado !== null): ?>
    <?php
    $paramDueno = $listado['tipo'] . '=' . rawurlencode((string) $listado['id']);
    $urlListado = static fn (array $extra = []): string => '/tracking?' . $paramDueno
        . ($listado['todas'] ? '&todas=1' : '') . ($extra !== [] ? '&' . http_build_query($extra) : '');
    $b = $listado['bultos'];
    $resumenBultos = array_filter([
        'Por recibir' => $b['receiving'] ?? 0,
        'En bodega' => $b['available'] ?? 0,
        'En orden de salida' => $b['reserved'] ?? 0,
        'En reempaque' => $b['in_repack'] ?? 0,
        'En traslado' => $b['in_transfer'] ?? 0,
        'En devolucion' => $b['in_return'] ?? 0,
        'Despachados / consumidos' => $listado['todas'] ? ($b['consumed'] ?? 0) : 0,
        'Devueltos al proveedor' => $listado['todas'] ? ($b['returned'] ?? 0) : 0,
    ]);
    ?>
    <div class="bg-white rounded-xl shadow p-6 max-w-6xl space-y-3 mb-4">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <p class="text-xs uppercase text-zinc-500"><?= $listado['tipo'] === 'cliente' ? 'Cliente' : 'Aduanero' ?></p>
                <p class="text-2xl font-bold text-black tracking-tight"><?= $h($listado['nombre']) ?></p>
                <p class="text-sm text-zinc-600"><?= $listado['total'] ?> carga(s) <?= $listado['todas'] ? 'en total' : 'en operacion (con bultos en bodega o en una OS/DO sin salir)' ?></p>
            </div>
            <div class="flex rounded-lg border border-zinc-300 overflow-hidden text-sm">
                <a href="/tracking?<?= $h($paramDueno) ?>" class="px-3 py-1.5 <?= !$listado['todas'] ? 'bg-amber-500 text-black font-medium' : 'text-zinc-700 hover:bg-slate-50' ?>">En operacion</a>
                <a href="/tracking?<?= $h($paramDueno) ?>&amp;todas=1" class="px-3 py-1.5 <?= $listado['todas'] ? 'bg-amber-500 text-black font-medium' : 'text-zinc-700 hover:bg-slate-50' ?>">Todas (incluye las que salieron)</a>
            </div>
        </div>
        <?php if ($resumenBultos !== []): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($resumenBultos as $lbl => $n): ?>
                    <span class="px-3 py-1 rounded-full text-sm font-medium bg-slate-100 text-zinc-700"><strong><?= $n ?></strong> <?= $h($lbl) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($listado['cargas'] === []): ?>
        <div class="bg-white rounded-xl shadow p-6 max-w-6xl text-sm text-zinc-500">
            <?= $listado['todas'] ? 'No tiene cargas registradas.' : 'No tiene cargas en operacion. Vea "Todas" para las que ya salieron.' ?>
        </div>
    <?php endif; ?>

    <div class="space-y-3 max-w-6xl">
        <?php foreach ($listado['cargas'] as $item): ?>
            <?php
            $view = $item['view'];
            $rastreo = $item['rastreo'];
            $movimientos = null;
            // Resumen en una linea: donde estan sus bultos.
            $partes = [];
            foreach ($rastreo['bultos'] as $bu) {
                $clave = $bu['ordenId'] !== null
                    ? $bu['orden'] . ($bu['ordenEstado'] === 'confirmada' ? ' (salio)' : '')
                    : ($bultoEstadoLabel[$bu['estado']] ?? $bu['estado']);
                $partes[$clave] = ($partes[$clave] ?? 0) + 1;
            }
            ?>
            <details class="group bg-white rounded-xl shadow" <?= count($listado['cargas']) === 1 ? 'open' : '' ?>>
                <summary class="cursor-pointer select-none list-none p-4 flex items-center gap-x-4 gap-y-1 flex-wrap hover:bg-amber-50 rounded-xl">
                    <span class="text-zinc-400 group-open:rotate-90 transition">&#9656;</span>
                    <span class="font-mono font-bold text-black"><?= $h($view->trackingNumero) ?></span>
                    <span class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 text-xs font-medium"><?= $h($view->estadoActualLabel) ?></span>
                    <?php if ($listado['tipo'] === 'aduanero'): ?><span class="text-sm text-black"><?= $h($rastreo['carga']['cliente']) ?></span><?php endif; ?>
                    <span class="text-sm text-zinc-600"><?= $h($rastreo['carga']['proveedor']) ?></span>
                    <span class="text-xs text-zinc-500">Ingreso <?= $h($fecha($rastreo['carga']['fechaIngreso'])) ?></span>
                    <span class="text-sm text-zinc-700 basis-full md:basis-auto md:ml-auto">
                        <?php if ($partes === []): ?>
                            Sin etiquetas (LPN)
                        <?php else: ?>
                            <?= $h(implode(' · ', array_map(static fn ($k, $n) => "{$n} {$k}", array_keys($partes), $partes))) ?>
                        <?php endif; ?>
                    </span>
                </summary>
                <div class="px-2 pb-2">
                    <?php require __DIR__ . '/_carga.php'; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>

    <?php if ($listado['paginas'] > 1): ?>
        <div class="flex items-center gap-2 mt-4 text-sm">
            <?php for ($p = 1; $p <= $listado['paginas']; $p++): ?>
                <a href="<?= $h($urlListado(['page' => $p])) ?>"
                   class="px-3 py-1 rounded border <?= $p === $listado['pagina'] ? 'bg-amber-500 border-amber-500 text-black font-medium' : 'border-zinc-300 text-zinc-700 hover:bg-slate-50' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php $view = null; ?>
<?php endif; ?>

<?php if ($view): ?>
    <?php require __DIR__ . '/_carga.php'; ?>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
