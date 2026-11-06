<?php
/** @var array<int, \Courier\Salida\Domain\SalidaOrden> $ordenes */
/** @var array<string, string> $mapaClientes */
/** @var array<string, array<int, string>> $clientesPorOrden ordenId => clientes de sus cargas */
/** @var array<string, array<int, string>> $aduanerosPorOrden ordenId => aduaneros de sus cargas */
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var array{estado: string, cliente_id: string} $filtros */
$pageTitle = 'Historial de salidas';
$pageSubtitle = 'Ordenes de salida creadas';
require __DIR__ . '/../partials/header.php';

$estados = [
    'pendiente' => 'Pendiente',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
];

$estadoColores = ['pendiente' => 'amber', 'confirmada' => 'emerald', 'cancelada' => 'red'];

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
?>

<?php if (in_array($_SESSION['usuario_rol'] ?? null, ['operaciones', 'gerente', 'admin'], true)): // armar salidas: trafico/gerencia ?>
<div class="flex justify-end mb-4">
    <?= header_button('/salidas', 'plus', 'Armar salida', 'amber') ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<?= card_header('filter', 'Filtros', 'amber') ?>
<form method="get" action="/salidas/historial" class="flex items-end gap-4 flex-wrap">
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Estado</label>
        <select name="estado" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm">
            <option value="">Todos</option>
            <?php foreach ($estados as $valor => $etiqueta): ?>
                <option value="<?= htmlspecialchars($valor) ?>" <?= $filtros['estado'] === $valor ? 'selected' : '' ?>>
                    <?= htmlspecialchars($etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
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
    <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
        <?= icon_small('filter') ?><span>Filtrar</span>
    </button>
    <a href="/salidas/historial" class="text-sm text-zinc-500 hover:text-black">Limpiar</a>
</form>
</div>

<?php
// Unificar: trafico/gerencia marcan varios DO pendientes y eligen en cual quedan (mismo contenedor).
$puedeUnificar = in_array($_SESSION['usuario_rol'] ?? null, ['operaciones', 'gerente', 'admin'], true);
?>
<?php if (!empty($errorUnificar)): ?>
    <div class="bg-red-50 border border-red-300 text-red-800 rounded-xl px-4 py-3 mb-4 text-sm"><?= htmlspecialchars($errorUnificar) ?></div>
<?php endif; ?>
<?php if ($puedeUnificar): ?>
<form method="post" action="/salidas/unificar" id="unificar-form"
      class="hidden bg-violet-50 border border-violet-300 rounded-xl px-4 py-3 mb-4 flex items-center gap-3 flex-wrap text-sm"
      onsubmit="var s=this.destino_id; return confirm('¿Unir los DO marcados en ' + s.options[s.selectedIndex].text + '? Sus cargas (con lo escaneado y las fotos) pasan a ese DO y los demas quedan cancelados.');">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Libraries\Csrf::token()) ?>">
    <span class="font-medium text-violet-900"><span id="unificar-cuantos">0</span> DO marcados.</span>
    <label for="unificar-destino" class="text-violet-900">Quedan unidos en</label>
    <select name="destino_id" id="unificar-destino" class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-1.5 text-sm"></select>
    <button type="submit" class="inline-flex items-center gap-2 bg-violet-600 text-white rounded-lg px-4 py-1.5 font-medium hover:bg-violet-500 transition">
        <?= icon_small('check') ?><span>Unificar</span>
    </button>
    <button type="button" id="unificar-limpiar" class="text-violet-700 hover:underline">Quitar marcas</button>
</form>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6" id="salidas-resultados" data-vivo-region="global">
    <?php if (empty($ordenes)): ?>
        <p class="text-sm text-zinc-500">No se encontraron ordenes de salida con estos filtros.</p>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <?php if ($puedeUnificar): ?><th class="py-2 pr-2 w-6" title="Marque varios DO pendientes para unificarlos"></th><?php endif; ?>
                    <th class="py-2 pr-4">DO</th>
                    <th class="py-2 pr-4">Aduanero</th>
                    <th class="py-2 pr-4">Contenedor</th>
                    <th class="py-2 pr-4">Naviera</th>
                    <th class="py-2 pr-4">Estado</th>
                    <th class="py-2 pr-4">Creada</th>
                    <th class="py-2 pr-4">Confirmada</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenes as $orden): ?>
                    <tr class="border-b border-slate-200">
                        <?php if ($puedeUnificar): ?>
                            <td class="py-2 pr-2">
                                <?php if ($orden->estado()->value === 'pendiente'): ?>
                                    <input type="checkbox" name="ordenes[]" form="unificar-form" value="<?= htmlspecialchars($orden->id()) ?>"
                                           data-do="<?= htmlspecialchars($orden->etiquetaDo() . ($orden->embarque()->contenedorFormateado() ? ' · ' . $orden->embarque()->contenedorFormateado() : '')) ?>"
                                           class="unificar-check w-4 h-4 accent-violet-600" title="Marcar para unificar">
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td class="py-2 pr-4 font-medium text-black whitespace-nowrap">
                            <a href="/salidas/<?= htmlspecialchars($orden->id()) ?>" class="text-amber-600 hover:underline">
                                <?= htmlspecialchars($orden->etiquetaDo()) ?>
                            </a>
                        </td>
                        <?php // Se muestra el aduanero; los clientes quedan al pasar el mouse. ?>
                        <td class="py-2 pr-4 text-zinc-700" title="Clientes: <?= htmlspecialchars(implode(', ', $clientesPorOrden[$orden->id()] ?? [$mapaClientes[$orden->clienteId() ?? ''] ?? '—'])) ?>"><?= htmlspecialchars(implode(', ', $aduanerosPorOrden[$orden->id()] ?? ['—'])) ?></td>
                        <td class="py-2 pr-4 font-mono text-zinc-700"><?= htmlspecialchars($orden->embarque()->contenedorFormateado() ?? '—') ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($orden->embarque()->navieraNombre ?? '—') ?></td>
                        <td class="py-2 pr-4"><?= badge($orden->estado()->label(), $estadoColores[$orden->estado()->value] ?? 'slate') ?><?php if ($orden->nota()): ?><span class="block text-xs text-zinc-500 mt-1"><?= htmlspecialchars($orden->nota()) ?></span><?php endif; ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($orden->createdAt()->format('Y-m-d H:i')) ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($orden->confirmedAt()?->format('Y-m-d H:i') ?? '—') ?></td>
                        <td class="py-2 text-right whitespace-nowrap"><?php if (puede_imprimir()): ?><?= icon_button('/salidas/' . $orden->id() . '/reporte', 'chart', 'Reporte de salida', 'slate') ?> <?= icon_button('/salidas/' . $orden->id() . '/manifiesto', 'printer', 'Imprimir manifiesto', 'slate') ?><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($puedeUnificar): ?>
<script>
// Unificar DO: la barra aparece con 2 o mas marcados y el selector ofrece solo los marcados.
(function () {
    var form = document.getElementById('unificar-form');
    var destino = document.getElementById('unificar-destino');
    var cuantos = document.getElementById('unificar-cuantos');
    var zona = document.getElementById('salidas-resultados');

    function actualizar() {
        var marcados = Array.prototype.slice.call(document.querySelectorAll('.unificar-check:checked'));
        var anterior = destino.value;
        destino.innerHTML = '';
        marcados.forEach(function (c) {
            var op = document.createElement('option');
            op.value = c.value;
            op.textContent = c.getAttribute('data-do');
            destino.appendChild(op);
        });
        if (anterior && destino.querySelector('option[value="' + anterior + '"]')) {
            destino.value = anterior;
        }
        cuantos.textContent = marcados.length;
        form.classList.toggle('hidden', marcados.length < 2);
        // Sin marcas, "En vivo" puede volver a refrescar la tabla.
        if (marcados.length === 0) {
            zona.removeAttribute('data-vivo-sucio');
        }
    }

    // Por delegacion: la tabla se reemplaza con "En vivo".
    zona.addEventListener('change', function (e) {
        if (e.target.classList.contains('unificar-check')) {
            actualizar();
        }
    });
    document.getElementById('unificar-limpiar').addEventListener('click', function () {
        document.querySelectorAll('.unificar-check:checked').forEach(function (c) { c.checked = false; });
        actualizar();
    });
    document.addEventListener('vivo:actualizado', actualizar);
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
