<?php
/**
 * Crear DO (Salidas, trafico): datos del contenedor y las OS (ordenes de salida de inventario)
 * que suben a el. La primera OS marcada recibe el DO; las demas se le unen con lo escaneado.
 *
 * @var array<int, \Courier\Salida\Domain\SalidaOrden> $ordenes OS pendientes sin DO
 * @var array<string, array<int, string>> $clientesPorOrden
 * @var array<string, array<int, string>> $aduanerosPorOrden
 * @var array<int, \Courier\Naviera\Domain\Naviera> $navieras
 * @var array<int, string> $seleccionadas
 * @var array<string, string> $embarque
 * @var string|null $error
 */
use App\Libraries\Csrf;

require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Crear DO';
$pageSubtitle = 'Contenedor y las OS que suben a el';
$backHref = '/salidas/historial';
require __DIR__ . '/../partials/header.php';

$valor = static fn (string $campo): string => htmlspecialchars($embarque[$campo] ?? '');
$claseInput = 'w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none';
?>

<?php if ($error !== null): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="/salidas/crear-do" id="form-crear-do">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <?= card_header('truck', '1. Contenedor', 'sky') ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Numero de DO</label>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-zinc-500">DO.</span>
                    <input type="number" name="numero_do" min="1" max="999999" placeholder="Siguiente" value="<?= $valor('numero_do') ?>" class="<?= $claseInput ?>">
                    <span class="text-sm font-medium text-zinc-500 whitespace-nowrap">/ <?= date('Y') ?></span>
                </div>
                <p class="text-xs text-zinc-500 mt-1">Vacio = toma el siguiente numero del ano.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Numero de contenedor</label>
                <input type="text" name="numero_contenedor" maxlength="15" placeholder="Ej: TGBU10548-6" value="<?= $valor('numero_contenedor') ?>" class="<?= $claseInput ?> uppercase">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Sello</label>
                <input type="text" name="numero_sello" maxlength="40" placeholder="Ej: SML G4187571" value="<?= $valor('numero_sello') ?>" class="<?= $claseInput ?> uppercase">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Naviera</label>
                <select name="naviera_id" class="<?= $claseInput ?>">
                    <option value="">Seleccione...</option>
                    <?php foreach ($navieras as $naviera): ?>
                        <option value="<?= htmlspecialchars($naviera->id()) ?>" <?= ($embarque['naviera_id'] ?? '') === $naviera->id() ? 'selected' : '' ?>><?= htmlspecialchars($naviera->nombre()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Barco</label>
                <input type="text" name="nombre_barco" maxlength="120" value="<?= $valor('nombre_barco') ?>" class="<?= $claseInput ?> uppercase">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Fecha de cargue</label>
                <input type="date" name="fecha_cargue" value="<?= $valor('fecha_cargue') ?>" class="<?= $claseInput ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Fecha de salida (ETD)</label>
                <input type="date" name="fecha_salida_etd" value="<?= $valor('fecha_salida_etd') ?>" class="<?= $claseInput ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Destino final</label>
                <input type="text" name="destino_final" maxlength="190" placeholder="Ej: Santo Domingo, Republica Dominicana" value="<?= $valor('destino_final') ?>" class="<?= $claseInput ?>">
            </div>
        </div>
        <p class="text-xs text-zinc-500 mt-3">Se puede completar o corregir despues en la salida, mientras no este cancelada.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 mb-4">
        <?= card_header('box', '2. OS que suben a este contenedor', 'amber') ?>
        <?php if ($ordenes === []): ?>
            <p class="text-sm text-zinc-500">No hay OS esperando DO. Inventario las crea en <a href="/salidas/ordenes" class="text-amber-600 hover:underline">Orden de Salida</a>.</p>
        <?php else: ?>
            <p class="text-xs text-zinc-500 -mt-2 mb-3">Marque una o varias: quedan todas en este DO, con lo que bodega ya escaneo.</p>
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
                                       <?= in_array($orden->id(), $seleccionadas, true) ? 'checked' : '' ?> class="crear-do-os w-4 h-4 accent-amber-500">
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
        <span class="text-sm text-zinc-700"><strong id="crear-do-os" class="text-black">0</strong> OS · <strong id="crear-do-bultos" class="text-black">0</strong> bultos</span>
        <button type="submit" id="crear-do-boton" disabled
                class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-5 py-2.5 font-medium hover:bg-amber-400 transition disabled:opacity-40 disabled:cursor-not-allowed">
            <?= icon_small('check') ?><span>Crear DO</span>
        </button>
    </div>
</form>

<script>
(function () {
    var checks = Array.prototype.slice.call(document.querySelectorAll('.crear-do-os'));
    var boton = document.getElementById('crear-do-boton');

    function contar() {
        var marcados = checks.filter(function (c) { return c.checked; });
        document.getElementById('crear-do-os').textContent = marcados.length;
        document.getElementById('crear-do-bultos').textContent = marcados.reduce(function (s, c) { return s + parseInt(c.getAttribute('data-bultos'), 10); }, 0);
        boton.disabled = marcados.length === 0;
    }

    checks.forEach(function (c) { c.addEventListener('change', contar); });
    contar();

    document.getElementById('form-crear-do').addEventListener('submit', function () {
        boton.disabled = true;
        boton.querySelector('span').textContent = 'Creando...';
    });
})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
