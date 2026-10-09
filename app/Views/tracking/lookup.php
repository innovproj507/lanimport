<?php
use App\Libraries\Csrf;
/** @var \Courier\Carga\Application\ConsultarTrackingPorNumero\TrackingView|null $view */
$pageTitle = 'Rastreo de carga';
$pageSubtitle = 'Consulte el estado de una carga por su numero de tracking';
require __DIR__ . '/../partials/header.php';
?>

<form method="post" action="/tracking/buscar" class="bg-white rounded-xl shadow p-6 max-w-xl flex gap-3 mb-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="text" name="tracking_numero" placeholder="Ej: CRX-2026-A1B2C3" required
           class="flex-1 rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
    <button type="submit" class="bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
        Buscar
    </button>
</form>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($view): ?>
    <div class="bg-white rounded-xl shadow p-6 max-w-2xl space-y-4">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="px-3 py-1 rounded-full bg-amber-500/10 text-amber-600 text-sm font-medium">
                <?= htmlspecialchars($view->estadoActualLabel) ?>
            </span>
            <?php if ($view->tipoServicio !== null): ?>
                <span class="text-sm text-zinc-500">Servicio: <?= htmlspecialchars(ucfirst($view->tipoServicio)) ?></span>
            <?php endif; ?>
            <?php
            $bultoEstadoClases = [
                'receiving' => 'bg-amber-500/10 text-amber-600',
                'available' => 'bg-emerald-500/10 text-emerald-600',
                'in_repack' => 'bg-indigo-500/10 text-indigo-600',
                'reserved' => 'bg-blue-500/10 text-blue-600',
                'quarantine' => 'bg-red-500/10 text-red-600',
                'consumed' => 'bg-slate-500/10 text-slate-600',
            ];
            ?>
            <?php foreach ($view->bultosPorEstado as $grupo): ?>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?= $bultoEstadoClases[$grupo['estado']] ?? 'bg-slate-500/10 text-slate-600' ?>">
                    <?= htmlspecialchars($grupo['cantidad'] . ' ' . $grupo['estadoLabel']) ?>
                </span>
            <?php endforeach; ?>
        </div>

        <?php if ($view->fechaEstimadaLlegada): ?>
            <p class="text-sm text-zinc-700">
                <strong>Fecha estimada de llegada:</strong> <?= htmlspecialchars($view->fechaEstimadaLlegada) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($view->lineas)): ?>
            <h2 class="font-semibold text-black pt-4 border-t border-slate-200">Productos a entregar</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                            <th class="py-2 pr-3">Descripcion</th>
                            <th class="py-2 pr-3">UoM</th>
                            <th class="py-2 pr-3">Cantidad</th>
                            <th class="py-2">Peso (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($view->lineas as $linea): ?>
                            <tr class="border-b border-slate-200">
                                <td class="py-2 pr-3 text-black font-medium"><?= htmlspecialchars($linea['descripcion']) ?></td>
                                <td class="py-2 pr-3 text-zinc-700"><?= htmlspecialchars($linea['unidadMedida']) ?></td>
                                <td class="py-2 pr-3 text-zinc-700"><?= htmlspecialchars((string) $linea['cantidad']) ?></td>
                                <td class="py-2 text-zinc-700"><?= htmlspecialchars((string) $linea['peso']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h2 class="font-semibold text-black pt-4 border-t border-slate-200">Historial</h2>
        <ol class="space-y-3">
            <?php foreach ($view->historial as $entry): ?>
                <li class="flex items-start gap-3">
                    <span class="mt-1 w-2 h-2 rounded-full bg-amber-400"></span>
                    <div>
                        <p class="text-sm font-medium text-black"><?= htmlspecialchars($entry['estadoLabel']) ?></p>
                        <p class="text-xs text-zinc-500"><?= htmlspecialchars($entry['fecha']) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
