<?php
use App\Libraries\Csrf;
/** @var array<int, \Courier\Lpn\Domain\Lpn> $lpns */
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var array<int, \Courier\Reempaque\Domain\TipoReempaque> $tipos */
/** @var array{cliente_id: string} $filtros */
$pageTitle = 'Reempaque';
$pageSubtitle = 'Selecciona los LPNs origen para iniciar una orden de reempaque';
require __DIR__ . '/../partials/header.php';
?>

<div class="flex justify-end mb-4">
    <a href="/reempaques/historial" class="inline-flex items-center gap-1 text-sm text-amber-600 hover:underline"><?= icon_small('clock', 'w-4 h-4') ?> Ver historial de ordenes</a>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<?= card_header('filter', 'Filtros', 'amber') ?>
<form method="get" action="/reempaques" class="flex items-end gap-4 flex-wrap">
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Cliente</label>
        <select name="cliente_id" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
            <option value="">Selecciona un cliente</option>
            <?php foreach ($clientes as $cliente): ?>
                <option value="<?= htmlspecialchars($cliente->id()) ?>" <?= $filtros['cliente_id'] === $cliente->id() ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cliente->nombreCompleto()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
        <?= icon_small('filter') ?><span>Filtrar</span>
    </button>
</form>
</div>

<form method="post" action="/reempaques">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" name="cliente_id" value="<?= htmlspecialchars($filtros['cliente_id']) ?>">

    <div class="bg-white rounded-xl shadow-sm p-6">
        <?php if ($filtros['cliente_id'] === ''): ?>
            <p class="text-sm text-amber-600">Selecciona un cliente en el filtro para ver sus LPNs disponibles.</p>
        <?php elseif (empty($lpns)): ?>
            <p class="text-sm text-zinc-500">Este cliente no tiene LPNs disponibles.</p>
        <?php else: ?>
            <div class="mb-4">
                <label class="block text-sm font-medium text-zinc-700 mb-1">Tipo de reempaque</label>
                <select name="tipo" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
                    <?php foreach ($tipos as $tipo): ?>
                        <option value="<?= htmlspecialchars($tipo->value) ?>"><?= htmlspecialchars($tipo->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 pr-4"></th>
                        <th class="py-2 pr-4">Codigo LPN</th>
                        <th class="py-2">Ubicacion actual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lpns as $lpn): ?>
                        <tr class="border-b border-slate-200">
                            <td class="py-2 pr-4">
                                <input type="checkbox" name="lpn_ids[]" value="<?= htmlspecialchars($lpn->id()) ?>">
                            </td>
                            <td class="py-2 pr-4 font-mono font-medium text-black"><?= htmlspecialchars((string) $lpn->codigo()) ?></td>
                            <td class="py-2 text-zinc-700"><?= htmlspecialchars((string) $lpn->ubicacionId()) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-4">
                <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
                    <?= icon_small('box') ?><span>Iniciar orden de reempaque</span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
