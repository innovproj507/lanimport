<?php
use App\Libraries\Csrf;
/** @var \Courier\Reempaque\Domain\ReempaqueOrden $orden */
/** @var string $clienteNombre */
/** @var array<int, \Courier\Lpn\Domain\Ubicacion> $ubicaciones */
/** @var array<string, \Courier\Lpn\Domain\Lpn|null> $lpnsPorId */
/** @var array<string, array{ancestros: array<int, array{lpnId: string, codigo: string}>, descendientes: array<int, array{lpnId: string, codigo: string}>}> $genealogiaPorDestino */
$pageTitle = 'Orden de reempaque ' . ($orden->codigo() ?? '');
$pageSubtitle = 'Cliente: ' . $clienteNombre . ' — Tipo: ' . $orden->tipo()->label();
require __DIR__ . '/../partials/header.php';

$pendiente = $orden->estado()->value === 'pendiente';
$completado = $orden->estado()->value === 'completado';
$cancelado = $orden->estado()->value === 'cancelado';
$estadoColor = $completado ? 'emerald' : ($cancelado ? 'red' : 'amber');

$lpnEstadoColores = [
    'receiving' => 'amber',
    'available' => 'emerald',
    'in_repack' => 'indigo',
    'reserved' => 'blue',
    'quarantine' => 'red',
    'consumed' => 'slate',
];
?>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <?= badge($orden->estado()->label(), $estadoColor) ?>

    <h3 class="mt-4 text-sm font-semibold text-zinc-700 flex items-center gap-2"><?= icon_small('box', 'w-4 h-4 text-zinc-500') ?> LPNs origen</h3>
    <table class="w-full text-sm mt-2">
        <thead>
            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                <th class="py-2 pr-4">Codigo</th>
                <th class="py-2">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orden->origenLpnIds() as $origenId): ?>
                <?php $lpn = $lpnsPorId[$origenId] ?? null; ?>
                <tr class="border-b border-slate-200">
                    <td class="py-2 pr-4 font-mono font-medium text-black"><?= htmlspecialchars((string) $lpn?->codigo()) ?></td>
                    <td class="py-2"><?= $lpn !== null ? badge($lpn->estado()->label(), $lpnEstadoColores[$lpn->estado()->value] ?? 'slate') : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($pendiente): ?>
        <form method="post" action="/reempaques/<?= htmlspecialchars($orden->id()) ?>/completar" class="mt-6 flex items-end gap-4 flex-wrap">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Cantidad de LPNs destino</label>
                <input type="number" name="cantidad_destinos" value="1" min="1" class="w-32 rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Ubicacion destino</label>
                <select name="ubicacion_id" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
                    <option value="">Sin asignar</option>
                    <?php foreach ($ubicaciones as $ubicacion): ?>
                        <option value="<?= htmlspecialchars($ubicacion->id()) ?>"><?= htmlspecialchars($ubicacion->codigo()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="inline-flex items-center gap-2 bg-emerald-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-emerald-400 transition">
                <?= icon_small('check') ?><span>Completar reempaque</span>
            </button>
        </form>
        <form method="post" action="/reempaques/<?= htmlspecialchars($orden->id()) ?>/cancelar" class="mt-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <button type="submit" class="inline-flex items-center gap-2 bg-red-500/10 text-red-600 rounded-lg px-4 py-2 text-sm font-medium hover:bg-red-500/20 transition">
                <?= icon_small('x') ?><span>Cancelar Orden</span>
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($completado): ?>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-zinc-700 flex items-center gap-2"><?= icon_small('box', 'w-4 h-4 text-emerald-600') ?> LPNs destino generados</h3>
        <table class="w-full text-sm mt-2">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">Codigo</th>
                    <th class="py-2 pr-4">Etiqueta</th>
                    <th class="py-2">Genealogia (LPNs origen)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orden->destinoLpnIds() as $destinoId): ?>
                    <?php $lpn = $lpnsPorId[$destinoId] ?? null; ?>
                    <tr class="border-b border-slate-200">
                        <td class="py-2 pr-4 font-mono font-medium text-black"><?= htmlspecialchars((string) $lpn?->codigo()) ?></td>
                        <td class="py-2 pr-4">
                            <a href="/uploads/lpns/<?= htmlspecialchars((string) $lpn?->codigo()) ?>.png" target="_blank" title="Ver etiqueta completa"
                               class="inline-block bg-white rounded p-1 border border-transparent hover:border-amber-400 transition">
                                <img src="/uploads/lpns/<?= htmlspecialchars((string) $lpn?->codigo()) ?>.png" alt="Etiqueta LPN" class="h-10">
                            </a>
                        </td>
                        <td class="py-2 text-zinc-700">
                            <?php $ancestros = $genealogiaPorDestino[$destinoId]['ancestros'] ?? []; ?>
                            <?= htmlspecialchars(implode(', ', array_map(fn ($a) => $a['codigo'], $ancestros))) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
