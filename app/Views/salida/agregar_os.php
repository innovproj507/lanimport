<?php
/**
 * Agregar OS a un DO que ya existe (trafico). Al DO no se le agregan cargas sueltas: se marcan
 * OS pendientes y pasan al DO con lo que bodega ya escaneo y sus fotos; cada OS desaparece.
 *
 * @var \Courier\Salida\Domain\SalidaOrden $destino
 * @var array<int, \Courier\Salida\Domain\SalidaOrden> $ordenes OS pendientes sin DO
 * @var array<string, array<int, string>> $clientesPorOrden
 * @var array<string, array<int, string>> $aduanerosPorOrden
 * @var array<int, string> $seleccionadas
 * @var string|null $error
 */
use App\Libraries\Csrf;

require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Agregar OS a ' . $destino->etiquetaDo();
$pageSubtitle = 'Las OS marcadas pasan a este DO con lo que bodega ya escaneo';
$backHref = '/salidas/' . $destino->id();
require __DIR__ . '/../partials/header.php';
$progresoDestino = $destino->progreso();
$confirmado = $destino->estado()->value === 'confirmada';
?>

<?php if ($error !== null): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <?= card_header('truck', 'Salida ' . $destino->etiquetaDo(), 'sky') ?>
    <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm text-zinc-700">
        <span>Contenedor: <strong class="text-black font-mono"><?= htmlspecialchars($destino->embarque()->contenedorFormateado() ?? '—') ?></strong></span>
        <span>Naviera: <strong class="text-black"><?= htmlspecialchars($destino->embarque()->navieraNombre ?? '—') ?></strong></span>
        <span>Ya lleva: <strong class="text-black"><?= $progresoDestino['total'] ?></strong> bultos (<?= $progresoDestino['escaneadas'] ?> escaneados)</span>
    </div>
    <?php if ($confirmado): ?>
        <p class="text-sm text-amber-800 bg-amber-50 rounded-lg px-3 py-2 mt-3">Este DO ya esta confirmado: solo se le pueden agregar OS con todos sus bultos escaneados (salen despachados con el).</p>
    <?php endif; ?>
</div>

<form method="post" action="/salidas/<?= htmlspecialchars($destino->id()) ?>/agregar-os" id="form-agregar-os">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="bg-white rounded-xl shadow-sm p-6 mb-4">
        <?= card_header('box', 'OS que se agregan a este DO', 'amber') ?>
        <?php if ($ordenes === []): ?>
            <p class="text-sm text-zinc-500">No hay OS pendientes. Se crean en <a href="/salidas/ordenes" class="text-amber-600 hover:underline">Orden de Salida</a>.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 pr-2 w-6"></th>
                        <th class="py-2 pr-4">OS</th>
                        <th class="py-2 pr-4">Aduanero</th>
                        <th class="py-2 pr-4">Clientes</th>
                        <th class="py-2 pr-4">Escaneo de bodega</th>
                        <th class="py-2 pr-4">Creada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordenes as $orden): $p = $orden->progreso(); $completa = $p['total'] > 0 && $p['escaneadas'] >= $p['total']; $clientes = $clientesPorOrden[$orden->id()] ?? []; ?>
                        <tr class="border-b border-slate-200 hover:bg-amber-50/40">
                            <td class="py-2 pr-2">
                                <input type="checkbox" name="os_ids[]" value="<?= htmlspecialchars($orden->id()) ?>" data-bultos="<?= $p['total'] ?>"
                                       <?= in_array($orden->id(), $seleccionadas, true) ? 'checked' : '' ?> class="agregar-os w-4 h-4 accent-amber-500">
                            </td>
                            <td class="py-2 pr-4 font-medium whitespace-nowrap">
                                <a href="/salidas/<?= htmlspecialchars($orden->id()) ?>" target="_blank" class="text-amber-600 hover:underline"><?= htmlspecialchars($orden->etiquetaDo()) ?></a>
                            </td>
                            <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars(implode(', ', $aduanerosPorOrden[$orden->id()] ?? ['—'])) ?></td>
                            <td class="py-2 pr-4 text-zinc-700" title="<?= htmlspecialchars(implode(', ', $clientes)) ?>">
                                <?= htmlspecialchars(implode(', ', array_slice($clientes, 0, 2)) . (count($clientes) > 2 ? ' +' . (count($clientes) - 2) . ' mas' : '')) ?: '—' ?>
                            </td>
                            <td class="py-2 pr-4 whitespace-nowrap"><span class="<?= $completa ? 'text-emerald-700 font-medium' : 'text-black' ?>"><?= $p['escaneadas'] ?> de <?= $p['total'] ?></span></td>
                            <td class="py-2 pr-4 text-zinc-700 whitespace-nowrap"><?= htmlspecialchars($orden->createdAt()->format('Y-m-d H:i')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="sticky bottom-0 bg-white rounded-xl shadow-lg p-4 flex flex-wrap items-center justify-between gap-3">
        <span class="text-sm text-zinc-700"><strong id="agregar-os-n" class="text-black">0</strong> OS · <strong id="agregar-os-bultos" class="text-black">0</strong> bultos</span>
        <button type="submit" id="agregar-os-boton" disabled
                class="inline-flex items-center gap-2 bg-emerald-500 text-black rounded-lg px-5 py-2.5 font-medium hover:bg-emerald-400 transition disabled:opacity-40 disabled:cursor-not-allowed">
            <?= icon_small('check') ?><span>Agregar a <?= htmlspecialchars($destino->etiquetaDo()) ?></span>
        </button>
    </div>
</form>

<script>
(function () {
    var checks = Array.prototype.slice.call(document.querySelectorAll('.agregar-os'));
    var boton = document.getElementById('agregar-os-boton');

    function contar() {
        var marcados = checks.filter(function (c) { return c.checked; });
        document.getElementById('agregar-os-n').textContent = marcados.length;
        document.getElementById('agregar-os-bultos').textContent = marcados.reduce(function (s, c) { return s + parseInt(c.getAttribute('data-bultos'), 10); }, 0);
        boton.disabled = marcados.length === 0;
    }

    checks.forEach(function (c) { c.addEventListener('change', contar); });
    contar();

    document.getElementById('form-agregar-os').addEventListener('submit', function () {
        boton.disabled = true;
        boton.querySelector('span').textContent = 'Agregando...';
    });
})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
