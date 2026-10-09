<?php
use App\Libraries\Csrf;
/** @var array<int, \Courier\Lpn\Domain\Lpn> $lpns */
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var array<int, \Courier\Aduanero\Domain\Aduanero> $aduaneros */
/** @var array{cliente_id: string, aduanero_identificador: string, ubicacion_id: string} $filtros */
$pageTitle = 'Salidas';
$pageSubtitle = 'Selecciona los LPNs disponibles para crear una orden de salida';
require __DIR__ . '/../partials/header.php';

$clienteFiltroEtiqueta = '';
foreach ($clientes as $c) {
    if ($c->id() === $filtros['cliente_id']) {
        $partes = [];
        if ($c->codigo()) {
            $partes[] = $c->codigo();
        }
        $partes[] = $c->nombre();
        if ($c->empresa()) {
            $partes[] = $c->empresa();
        }
        if ((string) $c->email() !== '') {
            $partes[] = (string) $c->email();
        }
        $clienteFiltroEtiqueta = implode(' — ', $partes);
        break;
    }
}

$aduaneroFiltroEtiqueta = '';
foreach ($aduaneros as $a) {
    if ($a->identificador() === $filtros['aduanero_identificador']) {
        $aduaneroFiltroEtiqueta = $a->identificador() . ' — ' . $a->nombre();
        break;
    }
}
?>

<div class="flex justify-end mb-4">
    <a href="/salidas/historial" class="inline-flex items-center gap-1 text-sm text-amber-600 hover:underline"><?= icon_small('clock', 'w-4 h-4') ?> Ver historial de ordenes</a>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<?= card_header('filter', 'Filtros', 'amber') ?>
<form method="get" action="/salidas" class="flex items-end gap-4 flex-wrap">
    <div class="relative" id="cliente-picker">
        <label class="block text-sm font-medium text-zinc-700 mb-1">Cliente</label>
        <input type="text" id="cliente-buscar" placeholder="Escriba para buscar cliente..." autocomplete="off"
               value="<?= htmlspecialchars($clienteFiltroEtiqueta) ?>"
               class="w-64 rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
        <input type="hidden" name="cliente_id" id="cliente-id-hidden" value="<?= htmlspecialchars($filtros['cliente_id']) ?>">
        <div id="cliente-resultados" class="hidden absolute z-20 mt-1 w-64 max-h-60 overflow-y-auto bg-white border border-zinc-300 rounded-lg shadow-lg"></div>
        <script type="application/json" id="clientes-data"><?= json_encode(array_map(
            fn ($cliente) => [
                'id' => $cliente->id(),
                'nombre' => $cliente->nombre(),
                'email' => (string) $cliente->email(),
                'empresa' => $cliente->empresa(),
                'codigo' => $cliente->codigo(),
            ],
            $clientes,
        ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    </div>
    <div class="relative" id="aduanero-picker">
        <label class="block text-sm font-medium text-zinc-700 mb-1">Aduanero</label>
        <input type="text" id="aduanero-buscar" placeholder="Escriba para buscar aduanero..." autocomplete="off"
               value="<?= htmlspecialchars($aduaneroFiltroEtiqueta) ?>"
               class="w-64 rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
        <input type="hidden" name="aduanero_identificador" id="aduanero-id-hidden" value="<?= htmlspecialchars($filtros['aduanero_identificador']) ?>">
        <div id="aduanero-resultados" class="hidden absolute z-20 mt-1 w-64 max-h-60 overflow-y-auto bg-white border border-zinc-300 rounded-lg shadow-lg"></div>
        <script type="application/json" id="aduaneros-data"><?= json_encode(array_map(
            fn ($aduanero) => [
                'identificador' => $aduanero->identificador(),
                'nombre' => $aduanero->nombre(),
            ],
            $aduaneros,
        ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    </div>
    <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
        <?= icon_small('filter') ?><span>Filtrar</span>
    </button>
    <a href="/salidas" class="text-sm text-zinc-500 hover:text-black">Limpiar</a>
</form>
</div>

<form method="post" action="/salidas" id="form-salida">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" name="cliente_id" id="salida-cliente-id" value="<?= htmlspecialchars($filtros['cliente_id']) ?>">
    <input type="hidden" name="aduanero_identificador" value="<?= htmlspecialchars($filtros['aduanero_identificador']) ?>">

    <div class="bg-white rounded-xl shadow-sm p-6">
        <?php if (empty($lpns)): ?>
            <p class="text-sm text-zinc-500">No hay LPNs disponibles con estos filtros.</p>
        <?php elseif ($filtros['cliente_id'] === ''): ?>
            <p class="text-sm text-amber-600">Selecciona un cliente en el filtro para poder crear una orden de salida (todos los LPNs de una orden deben ser del mismo cliente).</p>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 pr-4">
                            <input type="checkbox" id="salida-lpn-check-all">
                        </th>
                        <th class="py-2 pr-4">Codigo LPN</th>
                        <th class="py-2 pr-4">Cliente</th>
                        <th class="py-2">Carga (tracking)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lpns as $lpn): ?>
                        <tr class="border-b border-slate-200">
                            <td class="py-2 pr-4">
                                <input type="checkbox" class="salida-lpn-check" name="lpn_ids[]" value="<?= htmlspecialchars($lpn->id()) ?>">
                            </td>
                            <td class="py-2 pr-4 font-mono font-medium text-black"><?= htmlspecialchars((string) $lpn->codigo()) ?></td>
                            <td class="py-2 pr-4 text-zinc-700">
                                <?php
                                $clienteLpn = null;
                                foreach ($clientes as $cliente) {
                                    if ($cliente->id() === $lpn->clienteId()) {
                                        $clienteLpn = $cliente;
                                        break;
                                    }
                                }
                                ?>
                                <?= htmlspecialchars($clienteLpn?->nombreCompleto() ?? '—') ?>
                            </td>
                            <td class="py-2 text-zinc-700"><?= htmlspecialchars((string) $lpn->cargaId()) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="mt-4">
                <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
                    <?= icon_small('truck') ?><span>Crear orden de salida</span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var checkAll = document.getElementById('salida-lpn-check-all');

    if (!checkAll) {
        return;
    }

    var checks = document.querySelectorAll('.salida-lpn-check');

    checkAll.addEventListener('change', function () {
        checks.forEach(function (check) {
            check.checked = checkAll.checked;
        });
    });

    checks.forEach(function (check) {
        check.addEventListener('change', function () {
            checkAll.checked = Array.prototype.every.call(checks, function (c) { return c.checked; });
        });
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
