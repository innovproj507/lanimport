<?php
use App\Libraries\Csrf;
/** @var array<int, array<string, mixed>> $cargas cargas con bultos disponibles en la bodega */
/** @var string|null $bodega bodega de donde sale (null = hay que elegirla arriba) */
/** @var string|null $error */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Crear orden de devolucion';
$pageSubtitle = 'Bultos que se devuelven al proveedor en el camion que los viene a buscar';
$backHref = '/devoluciones';
require __DIR__ . '/../partials/header.php';
$h = static fn ($v): string => htmlspecialchars((string) $v);
$marcadas = array_map('strval', (array) ($_POST['carga_ids'] ?? []));
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30"><?= $h($error) ?></div>
<?php endif; ?>

<?php if ($bodega === null): ?>
    <div class="bg-amber-500/10 text-amber-800 text-sm rounded-lg px-4 py-3 mb-4 border border-amber-500/30">
        Esta viendo <strong>todas las bodegas</strong>. Elija arriba, junto a la fecha, la bodega <strong>de donde salen</strong> los bultos.
    </div>
<?php else: ?>
<form method="post" action="/devoluciones" id="form-devolucion" class="space-y-4">
    <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">

    <div class="bg-white rounded-xl shadow p-5">
        <?= card_header('truck', 'Devolucion y camion' . (hay_varias_bodegas() ? ' · sale de ' . bodega_nombre($bodega) : ''), 'orange') ?>
        <?php
        $transporte = \Courier\Devolucion\Domain\DatosTransporte::desde($_POST['transporte_empresa'] ?? null, $_POST['chofer'] ?? null, $_POST['placa'] ?? null, $_POST['cedula_chofer'] ?? null);
        $motivoActual = $_POST['motivo'] ?? null;
        $notaActual = $_POST['nota'] ?? null;
        require __DIR__ . '/_transporte.php';
        ?>
        <p class="text-xs text-zinc-500 mt-3">Los datos del camion, la nota y los documentos se pueden completar despues, en la orden, cuando llegue el camion.</p>
    </div>

    <div class="bg-white rounded-xl shadow p-5 overflow-x-auto">
        <?= card_header('box', 'Cargas con bultos disponibles', 'amber') ?>
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <input type="search" id="dev-filtro" placeholder="Filtrar por tracking, cliente o proveedor..." class="w-full md:w-96 rounded-lg border border-zinc-300 px-3 py-2 text-sm">
            <span class="text-sm text-zinc-600 ml-auto"><strong id="dev-cargas">0</strong> carga(s) · <strong id="dev-bultos">0</strong> bulto(s)</span>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-3"></th>
                    <th class="py-2 pr-3">Tracking</th>
                    <th class="py-2 pr-3">Cliente</th>
                    <th class="py-2 pr-3">Proveedor</th>
                    <th class="py-2 pr-3">Aduanero</th>
                    <th class="py-2 pr-3 text-right">Disponibles</th>
                    <th class="py-2 text-right">Bultos a devolver</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cargas === []): ?>
                    <tr><td colspan="7" class="py-4 text-zinc-500">No hay bultos disponibles (escaneados en recepcion y fuera de una orden) en esta bodega.</td></tr>
                <?php endif; ?>
                <?php foreach ($cargas as $c): ?>
                    <tr class="border-b border-slate-100" data-filtro="<?= $h(mb_strtolower($c['tracking'] . ' ' . $c['cliente'] . ' ' . $c['proveedor'])) ?>">
                        <td class="py-2 pr-3"><input type="checkbox" name="carga_ids[]" value="<?= $h($c['cargaId']) ?>" data-listos="<?= (int) $c['listos'] ?>"
                               <?= in_array($c['cargaId'], $marcadas, true) ? 'checked' : '' ?> class="dev-check rounded border-zinc-300 text-orange-500 focus:ring-orange-400"></td>
                        <td class="py-2 pr-3 font-medium text-black"><a href="/cargas/<?= $h($c['cargaId']) ?>" class="hover:underline" target="_blank"><?= $h($c['tracking']) ?></a></td>
                        <td class="py-2 pr-3"><?= $h($c['cliente']) ?></td>
                        <td class="py-2 pr-3"><?= $h($c['proveedor'] !== '' ? $c['proveedor'] : '—') ?></td>
                        <td class="py-2 pr-3"><?= $h($c['aduanero'] !== '' ? $c['aduanero'] : '—') ?></td>
                        <td class="py-2 pr-3 text-right"><?= (int) $c['listos'] ?></td>
                        <td class="py-2 text-right">
                            <input type="number" name="cantidad[<?= $h($c['cargaId']) ?>]" min="1" max="<?= (int) $c['listos'] ?>" placeholder="<?= (int) $c['listos'] ?>"
                                   value="<?= $h($_POST['cantidad'][$c['cargaId']] ?? '') ?>" class="dev-cantidad w-24 rounded-lg border border-zinc-300 px-2 py-1 text-right">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-xs text-zinc-500 mt-2">Puede mezclar cargas de varios clientes y proveedores. Si deja vacia la cantidad, van todos los bultos disponibles de la carga.</p>
    </div>

    <button type="submit" id="dev-crear" class="inline-flex items-center gap-2 bg-orange-600 text-white rounded-lg px-6 py-2.5 font-medium hover:bg-orange-500 disabled:opacity-40 disabled:cursor-not-allowed"><?= icon_small('check') ?>Crear orden de devolucion</button>
</form>
<script>
(function () {
    var checks = Array.prototype.slice.call(document.querySelectorAll('.dev-check'));
    function total() {
        var cargas = 0, bultos = 0;
        checks.forEach(function (c) {
            if (!c.checked) { return; }
            cargas++;
            var cant = c.closest('tr').querySelector('.dev-cantidad').value.trim();
            bultos += cant !== '' ? parseInt(cant, 10) || 0 : parseInt(c.getAttribute('data-listos'), 10);
        });
        document.getElementById('dev-cargas').textContent = cargas;
        document.getElementById('dev-bultos').textContent = bultos;
        document.getElementById('dev-crear').disabled = cargas === 0;
        return bultos;
    }
    document.getElementById('form-devolucion').addEventListener('input', total);
    document.getElementById('form-devolucion').addEventListener('change', total);
    document.getElementById('form-devolucion').addEventListener('submit', function (e) {
        if (!confirm('Se apartaran ' + total() + ' bulto(s) para devolver al proveedor. No podran salir en una OS mientras la orden este abierta. ¿Crear la orden?')) { e.preventDefault(); }
    });
    document.getElementById('dev-filtro').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('[data-filtro]').forEach(function (tr) {
            tr.classList.toggle('hidden', q !== '' && tr.getAttribute('data-filtro').indexOf(q) === -1);
        });
    });
    total();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
