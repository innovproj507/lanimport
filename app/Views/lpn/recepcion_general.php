<?php
use App\Libraries\Csrf;
/** @var array<int, array{cargaId: string, tracking: string, cliente: string, marca: ?string, fechaIngreso: string, total: int, recibidos: int}> $pendientes */
/** @var array<int, \Courier\Lpn\Domain\Ubicacion> $ubicaciones */
$pageTitle = 'Recepcion';
$pageSubtitle = 'Escanee las etiquetas pegadas a los bultos para dejarlos disponibles';
require __DIR__ . '/../partials/header.php';
$porRecibir = array_sum(array_map(static fn (array $p) => $p['total'] - $p['recibidos'], $pendientes));
?>

<div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-6">
    <?= card_header('scan', 'Escanear etiquetas', 'lime') ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
            <label for="lpn-scan-input" class="block text-sm font-medium text-zinc-700 mb-1">Codigo del LPN</label>
            <input type="text" id="lpn-scan-input" autocomplete="off" autofocus inputmode="text" autocapitalize="characters"
                   data-url="/recepcion/escanear"
                   placeholder="Escanee la etiqueta (o escriba el codigo y presione Enter)"
                   class="w-full rounded-lg border-2 border-lime-400 bg-white text-black text-lg px-4 py-3 focus:ring-2 focus:ring-lime-400 focus:outline-none">
        </div>
        <div>
            <label for="lpn-ubicacion-select" class="block text-sm font-medium text-zinc-700 mb-1">Ubicacion (opcional)</label>
            <select id="lpn-ubicacion-select" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-3 focus:ring-2 focus:ring-lime-400 focus:outline-none">
                <option value="">Sin ubicacion (bodega general)</option>
                <?php foreach ($ubicaciones as $ubicacion): ?>
                    <option value="<?= htmlspecialchars($ubicacion->id()) ?>"><?= htmlspecialchars($ubicacion->codigo()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <p id="lpn-scan-mensaje" class="hidden mt-4 rounded-lg px-4 py-3 text-base font-medium"></p>
    <ul id="lpn-scan-historial" class="mt-3 space-y-1 text-sm text-zinc-600"></ul>
    <input type="hidden" id="lpn-csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
</div>

<div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
    <div class="flex items-center justify-between gap-4 flex-wrap mb-4">
        <h2 class="font-semibold text-black">Cargas por recibir</h2>
        <span class="text-sm text-zinc-600"><span id="lpn-por-recibir"><?= $porRecibir ?></span> bultos por escanear</span>
    </div>

    <?php if ($pendientes === []): ?>
        <p class="text-sm text-zinc-500">No hay etiquetas pendientes. Cuando Trafico genere los LPNs de una carga, aparecera aqui.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 pr-4">Tracking</th>
                        <th class="py-2 pr-4">Cliente</th>
                        <th class="py-2 pr-4 hidden sm:table-cell">Marca</th>
                        <th class="py-2 pr-4 hidden md:table-cell">Ingreso</th>
                        <th class="py-2 pr-4 w-48">Recibidos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendientes as $p): $pct = $p['total'] > 0 ? (int) round($p['recibidos'] * 100 / $p['total']) : 0; ?>
                        <tr class="border-b border-slate-200" data-carga-id="<?= htmlspecialchars($p['cargaId']) ?>">
                            <td class="py-2 pr-4">
                                <a href="/cargas/<?= htmlspecialchars($p['cargaId']) ?>/recepcion" class="font-mono font-medium text-blue-700 hover:underline"><?= htmlspecialchars($p['tracking']) ?></a>
                            </td>
                            <td class="py-2 pr-4 text-black"><?= htmlspecialchars($p['cliente']) ?></td>
                            <td class="py-2 pr-4 text-zinc-600 hidden sm:table-cell"><?= htmlspecialchars($p['marca'] ?? '—') ?></td>
                            <td class="py-2 pr-4 text-zinc-600 hidden md:table-cell"><?= htmlspecialchars(date('d/m/Y', strtotime($p['fechaIngreso']))) ?></td>
                            <td class="py-2 pr-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full bg-slate-200 overflow-hidden">
                                        <div class="lpn-progreso-barra h-2 bg-lime-500" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <span class="lpn-progreso-texto text-xs text-zinc-700 whitespace-nowrap"><?= $p['recibidos'] ?> / <?= $p['total'] ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
