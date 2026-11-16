<?php
use App\Libraries\Csrf;
/** @var array<int, array<string, mixed>> $cargas cargas con bultos disponibles en la bodega origen */
/** @var array<int, \Courier\Bodega\Domain\Bodega> $destinos */
$pageTitle = 'Nuevo traslado';
$pageSubtitle = 'Pasar bultos disponibles a otra bodega';
$backHref = '/traslados';
require __DIR__ . '/../partials/header.php';
$h = static fn ($v): string => htmlspecialchars((string) $v);
$marcadas = array_map('strval', (array) ($_POST['carga_ids'] ?? []));
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-5xl"><?= $h($error) ?></div>
<?php endif; ?>

<?php if ($origen === null): ?>
    <div class="bg-amber-500/10 text-amber-800 text-sm rounded-lg px-4 py-3 mb-4 border border-amber-500/30 max-w-5xl">
        Esta viendo <strong>todas las bodegas</strong>. Elija arriba, junto a la fecha, la bodega <strong>de donde salen</strong> los bultos.
    </div>
<?php else: ?>
<form method="post" action="/traslados" class="max-w-5xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
    <div class="bg-white rounded-xl shadow p-5 grid gap-4 md:grid-cols-3">
        <div>
            <p class="text-xs text-zinc-500 uppercase">Sale de</p>
            <p class="text-lg font-semibold text-black"><?= $h(bodega_nombre($origen)) ?></p>
        </div>
        <div>
            <label class="block text-xs text-zinc-500 uppercase mb-1" for="destino">Va a</label>
            <select name="destino_bodega_id" id="destino" required class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <option value="">Seleccione...</option>
                <?php foreach ($destinos as $b): ?>
                    <option value="<?= $h($b->id()) ?>" <?= ($_POST['destino_bodega_id'] ?? '') === $b->id() ? 'selected' : '' ?>><?= $h($b->nombre()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-zinc-500 uppercase mb-1" for="nota">Nota (opcional)</label>
            <input type="text" name="nota" id="nota" maxlength="255" value="<?= $h($_POST['nota'] ?? '') ?>" placeholder="Ej: para cargar contenedor en Colon"
                   class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-5 overflow-x-auto">
        <?= card_header('box', 'Cargas con bultos disponibles en ' . bodega_nombre($origen), 'amber') ?>
        <input type="search" id="traslado-filtro" placeholder="Filtrar por tracking o cliente..." class="mb-3 w-full md:w-80 rounded-lg border border-zinc-300 px-3 py-2 text-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-3"></th>
                    <th class="py-2 pr-3">Tracking</th>
                    <th class="py-2 pr-3">Cliente</th>
                    <th class="py-2 pr-3">Aduanero</th>
                    <th class="py-2 pr-3 text-right">Disponibles</th>
                    <th class="py-2 text-right">Bultos a trasladar</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cargas === []): ?>
                    <tr><td colspan="6" class="py-4 text-zinc-500">No hay bultos disponibles (escaneados y libres) en esta bodega.</td></tr>
                <?php endif; ?>
                <?php foreach ($cargas as $c): ?>
                    <tr class="border-b border-slate-100" data-filtro="<?= $h(strtolower($c['tracking'] . ' ' . $c['cliente'])) ?>">
                        <td class="py-2 pr-3"><input type="checkbox" name="carga_ids[]" value="<?= $h($c['cargaId']) ?>" <?= in_array($c['cargaId'], $marcadas, true) ? 'checked' : '' ?> class="rounded border-zinc-300"></td>
                        <td class="py-2 pr-3 font-medium text-black"><?= $h($c['tracking']) ?></td>
                        <td class="py-2 pr-3"><?= $h($c['cliente']) ?></td>
                        <td class="py-2 pr-3"><?= $h($c['aduanero']) ?></td>
                        <td class="py-2 pr-3 text-right"><?= (int) $c['listos'] ?></td>
                        <td class="py-2 text-right">
                            <input type="number" name="cantidad[<?= $h($c['cargaId']) ?>]" min="1" max="<?= (int) $c['listos'] ?>" placeholder="<?= (int) $c['listos'] ?>"
                                   value="<?= $h($_POST['cantidad'][$c['cargaId']] ?? '') ?>" class="w-24 rounded-lg border border-zinc-300 px-2 py-1 text-right">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-xs text-zinc-500 mt-2">Si deja vacia la cantidad, van todos los disponibles de la carga.</p>
    </div>

    <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400"><?= icon_small('check') ?>Crear traslado</button>
</form>
<script>
document.getElementById('traslado-filtro').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('[data-filtro]').forEach(function (tr) {
        tr.classList.toggle('hidden', q !== '' && tr.getAttribute('data-filtro').indexOf(q) === -1);
    });
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
