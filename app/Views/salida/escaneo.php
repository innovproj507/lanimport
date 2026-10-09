<?php
use App\Libraries\Csrf;
/** @var \Courier\Salida\Domain\SalidaOrden $orden */
/** @var string $clienteNombre */
$pageTitle = 'Orden de salida ' . ($orden->codigo() ?? '');
$pageSubtitle = 'Cliente: ' . $clienteNombre;
require __DIR__ . '/../partials/header.php';

$progreso = $orden->progreso();
$confirmada = $orden->estado()->value === 'confirmada';
$cancelada = $orden->estado()->value === 'cancelada';
?>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex items-end gap-4 flex-wrap justify-between">
        <div class="flex items-end gap-4 flex-wrap">
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Escanear codigo de LPN</label>
                <input type="text" id="salida-scan-input" autocomplete="off" <?= $confirmada || $cancelada ? 'disabled' : 'autofocus' ?>
                       placeholder="Escanea o escribe el codigo y presiona Enter"
                       class="w-80 rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
            </div>
            <p id="salida-scan-mensaje" class="text-sm"></p>
        </div>
        <div class="text-right">
            <p class="text-sm text-zinc-500">Progreso</p>
            <p id="salida-progreso" class="text-lg font-bold text-black" data-escaneadas="<?= $progreso['escaneadas'] ?>" data-total="<?= $progreso['total'] ?>">
                <?= $progreso['escaneadas'] ?> de <?= $progreso['total'] ?> escaneados
            </p>
        </div>
    </div>

    <input type="hidden" id="salida-csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" id="salida-orden-id" value="<?= htmlspecialchars($orden->id()) ?>">

    <?php if (!$confirmada && !$cancelada): ?>
        <div class="mt-4 flex gap-3">
            <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/finalizar">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button type="submit" id="salida-confirmar-btn" <?= $progreso['escaneadas'] < $progreso['total'] ? 'disabled' : '' ?>
                        class="inline-flex items-center gap-2 bg-emerald-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-emerald-400 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <?= icon_small('check') ?><span>Confirmar Salida</span>
                </button>
            </form>
            <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/cancelar">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button type="submit" class="inline-flex items-center gap-2 bg-red-500/10 text-red-600 rounded-lg px-4 py-2 text-sm font-medium hover:bg-red-500/20 transition">
                    <?= icon_small('x') ?><span>Cancelar Orden</span>
                </button>
            </form>
        </div>
    <?php else: ?>
        <p class="mt-4 text-sm font-medium <?= $confirmada ? 'text-emerald-600' : 'text-red-600' ?>">
            Esta orden esta <?= htmlspecialchars($orden->estado()->label()) ?>.
        </p>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm p-6">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                <th class="py-2 pr-4">Codigo LPN</th>
                <th class="py-2">Estado</th>
            </tr>
        </thead>
        <tbody id="salida-tbody">
            <?php foreach ($orden->lineas() as $linea): ?>
                <tr class="border-b border-slate-200" data-codigo="<?= htmlspecialchars($linea->codigoLpn()) ?>">
                    <td class="py-2 pr-4 font-mono font-medium text-black"><?= htmlspecialchars($linea->codigoLpn()) ?></td>
                    <td class="py-2 salida-linea-estado">
                        <?= $linea->escaneado() ? badge('Escaneado', 'emerald') : badge('Pendiente', 'slate') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
