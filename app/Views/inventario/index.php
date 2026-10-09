<?php
/** @var array<int, \Courier\Lpn\Domain\Lpn> $lpns */
/** @var array<int, \Courier\Lpn\Domain\Ubicacion> $ubicaciones */
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var array<string, \Courier\Carga\Domain\Carga|null> $cargasPorId */
/** @var array{estado: string, ubicacion_id: string, cliente_id: string, carga_codigo: string} $filtros */
$pageTitle = 'Inventario';
$pageSubtitle = 'LPNs en bodega';
require __DIR__ . '/../partials/header.php';

$mapaUbicaciones = [];
foreach ($ubicaciones as $ubicacion) {
    $mapaUbicaciones[$ubicacion->id()] = $ubicacion->codigo();
}

$mapaClientes = [];
foreach ($clientes as $cliente) {
    $mapaClientes[$cliente->id()] = $cliente->nombreCompleto();
}

$estados = [
    'receiving' => 'En recepcion',
    'available' => 'Disponible',
    'in_repack' => 'En reempaque',
    'reserved' => 'Reservado',
    'quarantine' => 'En cuarentena',
    'consumed' => 'Consumido',
];

$estadoColores = [
    'receiving' => 'amber',
    'available' => 'emerald',
    'in_repack' => 'indigo',
    'reserved' => 'blue',
    'quarantine' => 'red',
    'consumed' => 'slate',
];

$mostrarDetalle = $filtros['carga_codigo'] !== '';

$grupos = [];
foreach ($lpns as $lpn) {
    $clave = $lpn->cargaId() ?? ('sin_carga_' . $lpn->clienteId());

    if (!isset($grupos[$clave])) {
        $grupos[$clave] = [
            'carga' => $lpn->cargaId() !== null ? ($cargasPorId[$lpn->cargaId()] ?? null) : null,
            'clienteId' => $lpn->clienteId(),
            'total' => 0,
            'porEstado' => [],
        ];
    }

    $grupos[$clave]['total']++;
    $estadoValor = $lpn->estado()->value;
    $grupos[$clave]['porEstado'][$estadoValor] = ($grupos[$clave]['porEstado'][$estadoValor] ?? 0) + 1;
}
?>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<?= card_header('filter', 'Filtros', 'amber') ?>
<form method="get" action="/inventario" class="flex items-end gap-4 flex-wrap">
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Estado</label>
        <select name="estado" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
            <option value="">Todos</option>
            <?php foreach ($estados as $valor => $etiqueta): ?>
                <option value="<?= htmlspecialchars($valor) ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>>
                    <?= htmlspecialchars($etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Ubicacion</label>
        <select name="ubicacion_id" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
            <option value="">Todas</option>
            <?php foreach ($ubicaciones as $ubicacion): ?>
                <option value="<?= htmlspecialchars($ubicacion->id()) ?>" <?= $filtros['ubicacion_id'] === $ubicacion->id() ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ubicacion->codigo()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Cliente</label>
        <select name="cliente_id" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
            <option value="">Todos</option>
            <?php foreach ($clientes as $cliente): ?>
                <option value="<?= htmlspecialchars($cliente->id()) ?>" <?= $filtros['cliente_id'] === $cliente->id() ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cliente->nombreCompleto()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Tracking de carga</label>
        <input type="text" name="carga_codigo" value="<?= htmlspecialchars($filtros['carga_codigo']) ?>"
               placeholder="CRX-..." class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
    </div>
    <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
        <?= icon_small('filter') ?><span>Filtrar</span>
    </button>
    <a href="/inventario" class="text-sm text-zinc-500 hover:text-black">Limpiar</a>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm p-6">
    <?php if (empty($lpns)): ?>
        <p class="text-sm text-zinc-500">No se encontraron LPNs con estos filtros.</p>
    <?php elseif ($mostrarDetalle): ?>
        <div class="mb-4">
            <a href="/inventario" class="text-sm text-amber-600 hover:underline">&larr; Volver al resumen agrupado</a>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">Codigo LPN</th>
                    <th class="py-2 pr-4">Estado</th>
                    <th class="py-2 pr-4">Ubicacion</th>
                    <th class="py-2 pr-4">Cliente</th>
                    <th class="py-2">Carga (tracking)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lpns as $lpn): ?>
                    <?php $carga = $lpn->cargaId() !== null ? ($cargasPorId[$lpn->cargaId()] ?? null) : null; ?>
                    <tr class="border-b border-slate-200">
                        <td class="py-2 pr-4 font-mono font-medium text-black"><?= htmlspecialchars((string) $lpn->codigo()) ?></td>
                        <td class="py-2 pr-4"><?= badge($lpn->estado()->label(), $estadoColores[$lpn->estado()->value] ?? 'slate') ?></td>
                        <td class="py-2 pr-4 text-zinc-700">
                            <?= htmlspecialchars($lpn->ubicacionId() !== null ? ($mapaUbicaciones[$lpn->ubicacionId()] ?? '—') : '—') ?>
                        </td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($mapaClientes[$lpn->clienteId()] ?? '—') ?></td>
                        <td class="py-2 text-zinc-700">
                            <?php if ($carga): ?>
                                <a href="/cargas/<?= htmlspecialchars($carga->id()) ?>" class="text-amber-600 hover:underline">
                                    <?= htmlspecialchars((string) $carga->trackingNumero()) ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">Carga (tracking)</th>
                    <th class="py-2 pr-4">Cliente</th>
                    <th class="py-2 pr-4">Total LPNs</th>
                    <th class="py-2">Por estado</th>
                    <th class="py-2 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grupos as $grupo): ?>
                    <tr class="border-b border-slate-200">
                        <td class="py-2 pr-4 text-zinc-700">
                            <?php if ($grupo['carga']): ?>
                                <a href="/cargas/<?= htmlspecialchars($grupo['carga']->id()) ?>" class="font-mono text-amber-600 hover:underline">
                                    <?= htmlspecialchars((string) $grupo['carga']->trackingNumero()) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-zinc-500">Sin carga asociada</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($mapaClientes[$grupo['clienteId']] ?? '—') ?></td>
                        <td class="py-2 pr-4 font-medium text-black"><?= $grupo['total'] ?></td>
                        <td class="py-2 space-x-1">
                            <?php foreach ($grupo['porEstado'] as $estadoValor => $cantidad): ?>
                                <?= badge($cantidad . ' ' . ($estados[$estadoValor] ?? $estadoValor), $estadoColores[$estadoValor] ?? 'slate') ?>
                            <?php endforeach; ?>
                        </td>
                        <td class="py-2 text-right whitespace-nowrap space-x-2">
                            <?php if ($grupo['carga'] && $grupo['carga']->facturaProveedorRuta()): ?>
                                <a href="/<?= htmlspecialchars($grupo['carga']->facturaProveedorRuta()) ?>" target="_blank" title="Ver factura del proveedor"
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500 text-white hover:bg-red-600 transition align-middle">
                                    <?= icon_small('receipt') ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($grupo['carga']): ?>
                                <a href="/inventario?carga_codigo=<?= urlencode((string) $grupo['carga']->trackingNumero()) ?>"
                                   class="text-xs text-amber-600 hover:underline">Ver LPNs</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
