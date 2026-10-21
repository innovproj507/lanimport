<?php
use App\Libraries\Csrf;
/**
 * @var array<int, array{cargaId: string, tracking: string, clienteId: string, cliente: string, marca: ?string,
 *     proveedor: string, fechaIngreso: string, dias: int, bultos: int, etiquetas: int, listos: int,
 *     porEscanear: int, enOtraSalida: int, peso: float, valor: float, m3: float, pesoTotal: float, valorTotal: float}> $cargas
 */
/**
 * @var array<int, array{lpnId: string, codigo: string, clienteId: string, cliente: string, marca: ?string,
 *     dias: int, bultos: int, cargas: array<int, string>, peso: float, valor: float, m3: float}> $cajas reempacadas listas
 */
/** @var array<int, \Courier\Naviera\Domain\Naviera> $navieras */
/** @var array<int, string> $seleccionadas */
/** @var array<string, int> $cantidades "Bultos a subir" ya escritos por carga (si hubo un error) */
/** @var array<string, string> $embarque */
/** @var string|null $error */
/** @var \Courier\Salida\Domain\SalidaOrden|null $ordenDestino si viene, se agregan cargas a esa salida pendiente */
require_once __DIR__ . '/../partials/view_helpers.php';
$ordenDestino ??= null;
if ($ordenDestino !== null) {
    $pageTitle = 'Agregar cargas a ' . $ordenDestino->etiquetaDo();
    $pageSubtitle = 'Mismo contenedor: los bultos nuevos se suman a la salida y bodega los escanea';
    $backHref = '/salidas/' . $ordenDestino->id();
} else {
    $pageTitle = 'Armar salida';
    $pageSubtitle = 'Contenedor y cargas que suben; bodega las escanea al cargar';
    $backHref = '/salidas/historial';
}
require __DIR__ . '/../partials/header.php';

$valor = static fn (string $campo): string => htmlspecialchars($embarque[$campo] ?? '');
$claseInput = 'w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none';
$n = static fn ($v, int $d = 0): string => number_format((float) $v, $d, '.', ',');
$listas = count(array_filter($cargas, static fn (array $c) => $c['listos'] > 0)) + count($cajas);
?>

<?php if ($error !== null): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= $ordenDestino !== null ? '/salidas/' . htmlspecialchars($ordenDestino->id()) . '/agregar' : '/salidas' ?>" id="form-armar-salida">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <?php if ($ordenDestino !== null): $progresoDestino = $ordenDestino->progreso(); ?>
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <?= card_header('truck', 'Salida ' . $ordenDestino->etiquetaDo(), 'sky') ?>
        <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm text-zinc-700">
            <span>Contenedor: <strong class="text-black font-mono"><?= htmlspecialchars($ordenDestino->embarque()->contenedorFormateado() ?? '—') ?></strong></span>
            <span>Naviera: <strong class="text-black"><?= htmlspecialchars($ordenDestino->embarque()->navieraNombre ?? '—') ?></strong></span>
            <span>Ya lleva: <strong class="text-black"><?= $progresoDestino['total'] ?></strong> bultos (<?= $progresoDestino['escaneadas'] ?> escaneados)</span>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <?= card_header('truck', '1. Contenedor', 'sky') ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-zinc-700 mb-1">Destino final</label>
                <input type="text" name="destino_final" maxlength="190" placeholder="Ej: Santo Domingo, Republica Dominicana" value="<?= $valor('destino_final') ?>" class="<?= $claseInput ?>">
            </div>
        </div>
        <p class="text-xs text-zinc-500 mt-3">Se puede completar o corregir despues, mientras la salida no este cancelada.</p>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm p-6 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <?= card_header('box', $ordenDestino !== null ? 'Cargas que se agregan al contenedor' : '2. Cargas que suben al contenedor', 'amber') ?>
            <div class="flex flex-wrap items-center gap-3">
                <input type="search" id="armar-buscar" placeholder="Buscar cliente, marca, tracking o compania..."
                       class="w-72 rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <label class="inline-flex items-center gap-2 text-sm text-zinc-700">
                    <input type="checkbox" id="armar-solo-listas" class="rounded border-zinc-300 text-amber-500 focus:ring-amber-400">
                    Solo listas para salir (<?= $listas ?>)
                </label>
            </div>
        </div>
        <p class="text-xs text-zinc-500 mb-4">
            Al marcar una carga suben sus bultos ya escaneados; en <strong>Bultos a subir</strong> se puede poner menos (por ejemplo 25 de 115) y el resto queda en bodega para otro contenedor.
            Los bultos sin escanear tambien se quedan en bodega como saldo.
            Las cargas en gris aun no pueden salir; el motivo esta en la ultima columna.
        </p>

        <?php if ($cargas === [] && $cajas === []): ?>
            <p class="text-sm text-zinc-500">No hay cargas en bodega.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                            <th class="py-2 pr-3 w-8"></th>
                            <th class="py-2 pr-3">Tracking</th>
                            <th class="py-2 pr-3">Cliente</th>
                            <th class="py-2 pr-3">Marca</th>
                            <th class="py-2 pr-3">Compania</th>
                            <th class="py-2 pr-3 text-right" title="Al marcar una carga se puede indicar cuantos de sus bultos suben a este contenedor">Bultos a subir</th>
                            <th class="py-2 pr-3 text-right">Kilos</th>
                            <th class="py-2 pr-3 text-right">Valor</th>
                            <th class="py-2 pr-3 text-right">Dias</th>
                            <th class="py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="armar-cargas">
                        <?php foreach ($cajas as $caja):
                            $marcada = in_array($caja['lpnId'], $seleccionadas, true);
                            $busqueda = mb_strtolower($caja['codigo'] . ' ' . $caja['cliente'] . ' ' . ($caja['marca'] ?? '') . ' reempaque ' . implode(' ', $caja['cargas']));
                            ?>
                            <tr class="border-b border-slate-100 hover:bg-amber-50 cursor-pointer" data-buscar="<?= htmlspecialchars($busqueda) ?>" data-lista="1">
                                <td class="py-2 pr-3">
                                    <input type="checkbox" name="caja_ids[]" value="<?= htmlspecialchars($caja['lpnId']) ?>"
                                           class="armar-check rounded border-zinc-300 text-amber-500 focus:ring-amber-400"
                                           data-cliente="<?= htmlspecialchars($caja['clienteId']) ?>" data-bultos="<?= $caja['bultos'] ?>"
                                           data-peso="<?= $caja['peso'] ?>" data-m3="<?= $caja['m3'] ?>" data-valor="<?= $caja['valor'] ?>"
                                           <?= $marcada ? 'checked' : '' ?>>
                                </td>
                                <td class="py-2 pr-3">
                                    <span class="font-mono text-black"><?= htmlspecialchars($caja['codigo']) ?></span>
                                    <span class="block text-xs text-zinc-500">Contiene: <?= htmlspecialchars(implode(', ', $caja['cargas'])) ?></span>
                                </td>
                                <td class="py-2 pr-3 text-black font-medium"><?= htmlspecialchars($caja['cliente']) ?></td>
                                <td class="py-2 pr-3"><?= htmlspecialchars($caja['marca'] ?? '—') ?></td>
                                <td class="py-2 pr-3 text-zinc-500">Reempaque</td>
                                <td class="py-2 pr-3 text-right"><?= $n($caja['bultos']) ?></td>
                                <td class="py-2 pr-3 text-right"><?= $n($caja['peso'], 2) ?></td>
                                <td class="py-2 pr-3 text-right"><?= $n($caja['valor'], 2) ?></td>
                                <td class="py-2 pr-3 text-right"><?= $caja['dias'] ?></td>
                                <td class="py-2 whitespace-nowrap"><?= badge('Caja reempacada', 'violet') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($cargas as $c):
                            $puede = $c['listos'] > 0;
                            $marcada = $puede && in_array($c['cargaId'], $seleccionadas, true);
                            $busqueda = mb_strtolower($c['tracking'] . ' ' . $c['cliente'] . ' ' . ($c['marca'] ?? '') . ' ' . $c['proveedor']);
                            ?>
                            <tr class="border-b border-slate-100 <?= $puede ? 'hover:bg-amber-50 cursor-pointer' : 'text-zinc-400 bg-slate-50' ?>"
                                data-buscar="<?= htmlspecialchars($busqueda) ?>" data-lista="<?= $puede ? '1' : '0' ?>">
                                <td class="py-2 pr-3">
                                    <input type="checkbox" name="carga_ids[]" value="<?= htmlspecialchars($c['cargaId']) ?>"
                                           class="armar-check rounded border-zinc-300 text-amber-500 focus:ring-amber-400 disabled:opacity-30"
                                           data-cliente="<?= htmlspecialchars($c['clienteId']) ?>" data-bultos="<?= $c['listos'] ?>"
                                           data-peso="<?= $c['peso'] ?>" data-m3="<?= $c['m3'] ?>" data-valor="<?= $c['valor'] ?>"
                                           <?= $puede ? '' : 'disabled' ?> <?= $marcada ? 'checked' : '' ?>>
                                </td>
                                <td class="py-2 pr-3 font-mono <?= $puede ? 'text-black' : '' ?>"><a href="/cargas/<?= htmlspecialchars($c['cargaId']) ?>" class="hover:underline" onclick="event.stopPropagation()"><?= htmlspecialchars($c['tracking']) ?></a></td>
                                <td class="py-2 pr-3 <?= $puede ? 'text-black font-medium' : '' ?>"><?= htmlspecialchars($c['cliente']) ?></td>
                                <td class="py-2 pr-3"><?= htmlspecialchars($c['marca'] ?? '—') ?></td>
                                <td class="py-2 pr-3"><?= htmlspecialchars($c['proveedor']) ?></td>
                                <td class="py-2 pr-3 text-right whitespace-nowrap">
                                    <?php if ($puede): ?>
                                        <?php // Trafico decide cuantos bultos de la carga suben a este contenedor; el resto queda en bodega. ?>
                                        <input type="number" name="cantidad[<?= htmlspecialchars($c['cargaId']) ?>]" min="1" max="<?= $c['listos'] ?>" step="1"
                                               value="<?= (int) ($cantidades[$c['cargaId']] ?? $c['listos']) ?>" data-listos="<?= $c['listos'] ?>"
                                               title="Bultos de esta carga que suben a este contenedor (de <?= $c['listos'] ?> listos)"
                                               class="armar-cantidad w-20 rounded-lg border border-zinc-300 bg-white text-black text-right px-2 py-1 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none disabled:bg-slate-100 disabled:text-zinc-400"
                                               <?= $marcada ? '' : 'disabled' ?>>
                                        <span class="text-xs text-zinc-500">de <?= $n($c['listos']) ?></span>
                                        <?php if ($c['listos'] < $c['bultos']): ?>
                                            <span class="block text-xs text-zinc-400"><?= $n($c['bultos']) ?> en total</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= $n($c['bultos']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 pr-3 text-right"><?= $n($puede ? $c['peso'] : $c['pesoTotal'], 2) ?></td>
                                <td class="py-2 pr-3 text-right"><?= $n($puede ? $c['valor'] : $c['valorTotal'], 2) ?></td>
                                <td class="py-2 pr-3 text-right <?= $c['dias'] > 60 ? 'text-red-600 font-medium' : '' ?>"><?= $c['dias'] ?></td>
                                <td class="py-2 whitespace-nowrap">
                                    <?php if ($puede && $c['listos'] >= $c['bultos']): ?>
                                        <?= badge('Lista', 'emerald') ?>
                                    <?php elseif ($puede): ?>
                                        <?= badge('Parcial', 'amber') ?>
                                        <span class="text-xs text-zinc-500 ml-1"><?= $n($c['bultos'] - $c['listos']) ?> quedan</span>
                                    <?php elseif ($c['etiquetas'] === 0): ?>
                                        <?= badge('Sin etiquetas', 'red') ?>
                                        <a href="/cargas/<?= htmlspecialchars($c['cargaId']) ?>" class="text-xs text-blue-700 underline ml-1" onclick="event.stopPropagation()">Generar LPNs</a>
                                    <?php elseif ($c['porEscanear'] > 0): ?>
                                        <?= badge('Por escanear', 'amber') ?>
                                        <a href="/cargas/<?= htmlspecialchars($c['cargaId']) ?>/recepcion" class="text-xs text-blue-700 underline ml-1" onclick="event.stopPropagation()">Recepcion</a>
                                    <?php elseif ($c['enOtraSalida'] > 0): ?>
                                        <?= badge('En otra salida', 'blue') ?>
                                    <?php elseif ($c['enReempaque'] > 0): ?>
                                        <?= badge('En reempaque', 'indigo') ?>
                                    <?php elseif ($c['enCaja'] > 0): ?>
                                        <?= badge('En caja reempacada', 'violet') ?>
                                        <span class="text-xs text-zinc-500 ml-1">sale con su caja</span>
                                    <?php else: ?>
                                        <?= badge('Sin bultos', 'slate') ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p id="armar-sin-resultados" class="hidden text-sm text-zinc-500 mt-3">Ninguna carga coincide con la busqueda.</p>
        <?php endif; ?>
    </div>

    <!-- Totales de lo seleccionado, como el pie del manifiesto. -->
    <div class="sticky bottom-0 z-20 bg-white rounded-xl border border-slate-200 shadow-[0_-4px_12px_rgba(0,0,0,0.08)]">
        <div class="px-6 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-zinc-600">
                <span><strong id="tot-cargas" class="text-black">0</strong> cargas</span>
                <span><strong id="tot-clientes" class="text-black">0</strong> clientes</span>
                <span><strong id="tot-bultos" class="text-black">0</strong> bultos</span>
                <span><strong id="tot-peso" class="text-black">0.00</strong> kg</span>
                <span><strong id="tot-m3" class="text-black">0.000</strong> m³</span>
                <span>$<strong id="tot-valor" class="text-black">0.00</strong></span>
            </div>
            <button type="submit" id="armar-crear" disabled
                    class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition disabled:opacity-40 disabled:cursor-not-allowed">
                <?= icon_small('check') ?><span><?= $ordenDestino !== null ? 'Agregar a ' . htmlspecialchars($ordenDestino->numeroDo()) : 'Crear salida (DO)' ?></span>
            </button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.getElementById('armar-cargas');

    if (!tbody) {
        return;
    }

    var buscar = document.getElementById('armar-buscar');
    var soloListas = document.getElementById('armar-solo-listas');
    var sinResultados = document.getElementById('armar-sin-resultados');
    var crear = document.getElementById('armar-crear');
    var formato = function (v, d) { return Number(v).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); };

    function totales() {
        var marcadas = tbody.querySelectorAll('.armar-check:checked');
        var t = { bultos: 0, peso: 0, m3: 0, valor: 0 };
        var clientes = {};

        marcadas.forEach(function (c) {
            // Si trafico sube solo parte de la carga, kilos, m3 y valor van en proporcion.
            var cantidad = c.closest('tr').querySelector('.armar-cantidad');
            var listos = Number(c.dataset.bultos);
            var sube = cantidad ? Math.min(listos, Math.max(0, parseInt(cantidad.value, 10) || 0)) : listos;
            var proporcion = listos > 0 ? sube / listos : 0;

            t.bultos += sube;
            t.peso += Number(c.dataset.peso) * proporcion;
            t.m3 += Number(c.dataset.m3) * proporcion;
            t.valor += Number(c.dataset.valor) * proporcion;
            clientes[c.dataset.cliente] = true;
        });

        document.getElementById('tot-cargas').textContent = marcadas.length;
        document.getElementById('tot-clientes').textContent = Object.keys(clientes).length;
        document.getElementById('tot-bultos').textContent = formato(t.bultos, 0);
        document.getElementById('tot-peso').textContent = formato(t.peso, 2);
        document.getElementById('tot-m3').textContent = formato(t.m3, 3);
        document.getElementById('tot-valor').textContent = formato(t.valor, 2);
        crear.disabled = marcadas.length === 0;

        tbody.querySelectorAll('tr').forEach(function (fila) {
            var check = fila.querySelector('.armar-check');
            var cantidad = fila.querySelector('.armar-cantidad');
            fila.classList.toggle('bg-amber-100', check.checked);

            // "Bultos a subir" solo se edita (y se envia) en las cargas marcadas.
            if (cantidad) {
                cantidad.disabled = !check.checked;
            }
        });
    }

    function filtrar() {
        var texto = buscar.value.trim().toLowerCase();
        var visibles = 0;

        tbody.querySelectorAll('tr').forEach(function (fila) {
            var coincide = (texto === '' || fila.dataset.buscar.indexOf(texto) !== -1)
                && (!soloListas.checked || fila.dataset.lista === '1');
            // Lo ya marcado siempre se ve, para no perderlo al filtrar.
            var mostrar = coincide || fila.querySelector('.armar-check').checked;
            fila.classList.toggle('hidden', !mostrar);
            visibles += mostrar ? 1 : 0;
        });

        sinResultados.classList.toggle('hidden', visibles > 0);
    }

    // Clic en cualquier parte de la fila marca la carga (si se puede).
    tbody.addEventListener('click', function (evento) {
        // Escribir en "Bultos a subir" no marca ni desmarca la carga.
        if (evento.target.closest('.armar-cantidad')) {
            return;
        }

        var fila = evento.target.closest('tr');
        var check = fila ? fila.querySelector('.armar-check') : null;

        if (check && !check.disabled && evento.target !== check) {
            check.checked = !check.checked;
        }

        totales();
    });

    tbody.addEventListener('input', function (evento) {
        if (evento.target.classList.contains('armar-cantidad')) {
            totales();
        }
    });

    buscar.addEventListener('input', filtrar);
    soloListas.addEventListener('change', filtrar);
    document.getElementById('form-armar-salida').addEventListener('submit', function () {
        crear.disabled = true;
        crear.querySelector('span').textContent = <?= $ordenDestino !== null ? "'Agregando...'" : "'Creando...'" ?>;
    });

    totales();
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
