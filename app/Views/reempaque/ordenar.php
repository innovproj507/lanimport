<?php
use App\Libraries\Csrf;
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var \Courier\Cliente\Domain\Cliente|null $cliente */
/**
 * @var array<int, array{cargaId: string, tracking: string, marca: ?string, proveedor: string, dias: int, bultos: int,
 *     etiquetas: int, listos: int, porEscanear: int, enOtraSalida: int, enReempaque: int, enCaja: int, peso: float, valor: float}> $cargas
 */
/** @var array<int, \Courier\Reempaque\Domain\TipoReempaque> $tipos */
/** @var array<int, string> $seleccionadas */
/** @var string $tipo */
/** @var string $instrucciones */
/** @var string|null $error */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Ordenar reempaque';
$pageSubtitle = 'Trafico indica que se reempaca; bodega lo arma en la app escaneando caja por caja';
$headerActions = header_button('/reempaques/historial', 'clock', 'Ordenes de reempaque', 'slate');
require __DIR__ . '/../partials/header.php';

$n = static fn ($v, int $d = 0): string => number_format((float) $v, $d, '.', ',');
$etiquetaCliente = static fn ($c): string => trim(($c->codigo() ? $c->codigo() . ' — ' : '') . $c->nombreCompleto() . ($c->empresa() ? ' — ' . $c->empresa() : ''));
$claseInput = 'w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none';
?>

<?php if ($error !== null): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php // Que es un reempaque: abierto mientras no se ha elegido cliente; despues, plegado. ?>
<details class="bg-white rounded-xl shadow-sm mb-6 group" <?= $cliente === null ? 'open' : '' ?>>
    <summary class="cursor-pointer select-none px-6 py-4 font-semibold text-black flex items-center gap-2">
        <?= icon_small('box', 'w-4 h-4 text-violet-600') ?> ¿Que es un reempaque?
        <span class="text-sm font-normal text-zinc-500">· cambiar como vienen empacados los bultos de un cliente antes de despacharlos</span>
    </summary>
    <div class="px-6 pb-6 space-y-5">
        <div class="grid gap-3 md:grid-cols-3">
            <?php foreach ([
                ['1', 'Trafico ordena', 'Aqui: elige el cliente, sus cargas y que hay que hacer. Esos bultos quedan apartados ("En reempaque") y no pueden salir en una OS mientras tanto.'],
                ['2', 'Bodega decide y lo arma en la PDA', 'Bodega decide cuantas cajas o paletas arma, segun el tamano. En la app › Reempaque: abre una caja o paleta, escanea lo que mete, la cierra (sale su etiqueta LPN-R…) y abre otra si hace falta. Al final, Terminar.'],
                ['3', 'La caja nueva se despacha', 'La caja aparece en Crear OS como "Caja reempacada", con lo que contiene. Rastreo de cada carga dice en que caja quedo cada bulto.'],
            ] as [$num, $titulo, $texto]): ?>
                <div class="rounded-lg border border-slate-200 p-4">
                    <p class="flex items-center gap-2 font-semibold text-black"><span class="w-6 h-6 rounded-full bg-violet-600 text-white text-xs flex items-center justify-center"><?= $num ?></span><?= $titulo ?></p>
                    <p class="text-sm text-zinc-600 mt-1"><?= $texto ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div>
            <p class="text-sm font-semibold text-black mb-2">Ejemplos</p>
            <div class="grid gap-3 md:grid-cols-3 text-sm">
                <div class="rounded-lg bg-amber-50 border border-amber-200 p-3">
                    <p class="font-semibold text-black">Consolidar</p>
                    <p class="text-2xl my-1">📦📦📦📦 → 🧱</p>
                    <p class="text-zinc-700">El cliente tiene muchas cajas sueltas: se juntan en paletas. Bodega decide si salen <strong>1 o 2 paletas</strong> segun como queden.</p>
                </div>
                <div class="rounded-lg bg-sky-50 border border-sky-200 p-3">
                    <p class="font-semibold text-black">Dividir</p>
                    <p class="text-2xl my-1">🧱 → 📦📦</p>
                    <p class="text-zinc-700">Una paleta muy grande no cabe en el contenedor: se parte en las cajas que bodega vea necesarias. Aqui un mismo bulto puede quedar repartido en varias.</p>
                </div>
                <div class="rounded-lg bg-rose-50 border border-rose-200 p-3">
                    <p class="font-semibold text-black">Cambiar caja</p>
                    <p class="text-2xl my-1">💥📦 → 📦</p>
                    <p class="text-zinc-700">Una caja llego rota: su contenido pasa a una <strong>caja nueva</strong>, con etiqueta nueva.</p>
                </div>
            </div>
        </div>
    </div>
</details>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <?= card_header('users', '1. Cliente', 'amber') ?>
    <?php if ($cliente !== null): ?>
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-300 px-4 py-2 font-semibold text-black"><?= htmlspecialchars($etiquetaCliente($cliente)) ?></span>
            <a href="/reempaques" class="text-sm text-blue-700 underline">Cambiar cliente</a>
        </div>
    <?php elseif ($clientesConBultos === []): ?>
        <p class="text-sm text-zinc-500">Ningun cliente tiene bultos listos para reempacar (escaneados en bodega y libres).</p>
    <?php else: ?>
        <p class="text-xs text-zinc-500 mb-2">Un reempaque es siempre de un solo cliente. Aqui salen solo los clientes con bultos listos para reempacar.</p>
        <input type="search" id="reempaque-cliente-buscar" autocomplete="off" placeholder="Buscar cliente o marca..." class="<?= $claseInput ?> max-w-md mb-3">
        <div class="max-h-96 overflow-y-auto border border-slate-200 rounded-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-slate-50">
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 px-3">Cliente</th>
                        <th class="py-2 px-3">Marcas</th>
                        <th class="py-2 px-3 text-right">Cargas</th>
                        <th class="py-2 px-3 text-right">Bultos listos</th>
                        <th class="py-2 px-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientesConBultos as $cb): $marcas = implode(', ', array_keys($cb['marcas'])); ?>
                        <tr class="border-b border-slate-100 hover:bg-amber-50 cursor-pointer" data-cliente-fila data-buscar="<?= htmlspecialchars(mb_strtolower($cb['nombre'] . ' ' . $marcas)) ?>"
                            onclick="location.href='/reempaques?cliente_id=<?= htmlspecialchars(rawurlencode($cb['id'])) ?>'">
                            <td class="py-2 px-3 font-medium text-black"><?= htmlspecialchars($cb['nombre']) ?></td>
                            <td class="py-2 px-3 text-zinc-600"><?= htmlspecialchars($marcas !== '' ? $marcas : '—') ?></td>
                            <td class="py-2 px-3 text-right"><?= $cb['cargas'] ?></td>
                            <td class="py-2 px-3 text-right font-semibold"><?= $n($cb['bultos']) ?></td>
                            <td class="py-2 px-3 text-right"><span class="text-amber-700 font-medium">Elegir →</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($cliente !== null): ?>
<form method="post" action="/reempaques" id="form-reempaque">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" name="cliente_id" value="<?= htmlspecialchars($cliente->id()) ?>">

    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <?= card_header('box', '2. Cargas a reempacar', 'amber') ?>
        <p class="text-xs text-zinc-500 mb-4">Entran los bultos ya escaneados en bodega de cada carga marcada. Las cargas en gris todavia no se pueden reempacar.</p>
        <?php if ($cargas === []): ?>
            <p class="text-sm text-zinc-500">Este cliente no tiene cargas en bodega.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                            <th class="py-2 pr-3 w-8"></th>
                            <th class="py-2 pr-3">Tracking</th>
                            <th class="py-2 pr-3">Marca</th>
                            <th class="py-2 pr-3">Compania</th>
                            <th class="py-2 pr-3 text-right">Bultos</th>
                            <th class="py-2 pr-3 text-right">Kilos</th>
                            <th class="py-2 pr-3 text-right">Dias</th>
                            <th class="py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="reempaque-cargas">
                        <?php foreach ($cargas as $c): $puede = $c['listos'] > 0; ?>
                            <tr class="border-b border-slate-100 <?= $puede ? 'hover:bg-amber-50 cursor-pointer' : 'text-zinc-400 bg-slate-50' ?>">
                                <td class="py-2 pr-3">
                                    <input type="checkbox" name="carga_ids[]" value="<?= htmlspecialchars($c['cargaId']) ?>" data-bultos="<?= $c['listos'] ?>"
                                           class="reempaque-check rounded border-zinc-300 text-amber-500 focus:ring-amber-400 disabled:opacity-30"
                                           <?= $puede ? '' : 'disabled' ?> <?= $puede && in_array($c['cargaId'], $seleccionadas, true) ? 'checked' : '' ?>>
                                </td>
                                <td class="py-2 pr-3 font-mono <?= $puede ? 'text-black' : '' ?>"><?= htmlspecialchars($c['tracking']) ?></td>
                                <td class="py-2 pr-3"><?= htmlspecialchars($c['marca'] ?? '—') ?></td>
                                <td class="py-2 pr-3"><?= htmlspecialchars($c['proveedor']) ?></td>
                                <td class="py-2 pr-3 text-right"><?= $puede && $c['listos'] < $c['bultos'] ? $n($c['listos']) . ' de ' . $n($c['bultos']) : $n($c['bultos']) ?></td>
                                <td class="py-2 pr-3 text-right"><?= $n($c['peso'], 2) ?></td>
                                <td class="py-2 pr-3 text-right"><?= $c['dias'] ?></td>
                                <td class="py-2 whitespace-nowrap">
                                    <?php if ($puede): ?>
                                        <?= badge($c['listos'] < $c['bultos'] ? 'Parcial' : 'Lista', $c['listos'] < $c['bultos'] ? 'amber' : 'emerald') ?>
                                    <?php elseif ($c['etiquetas'] === 0): ?>
                                        <?= badge('Sin etiquetas', 'red') ?>
                                    <?php elseif ($c['porEscanear'] > 0): ?>
                                        <?= badge('Por escanear', 'amber') ?>
                                    <?php elseif ($c['enReempaque'] > 0): ?>
                                        <?= badge('En otro reempaque', 'indigo') ?>
                                    <?php elseif ($c['enCaja'] > 0): ?>
                                        <?= badge('Ya reempacada', 'violet') ?>
                                    <?php elseif ($c['enOtraSalida'] > 0): ?>
                                        <?= badge('En una salida', 'blue') ?>
                                    <?php else: ?>
                                        <?= badge('Sin bultos', 'slate') ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <?= card_header('pencil', '3. Que tipo de trabajo es', 'amber') ?>
        <p class="text-xs text-zinc-500 mb-3">Trafico indica que hacer; <strong>bodega decide cuantas cajas o paletas arma</strong> segun el tamano de la mercancia.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <?php foreach ($tipos as $i => $t): ?>
                <label class="flex items-start gap-3 rounded-lg border border-zinc-300 p-3 cursor-pointer has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                    <input type="radio" name="tipo" value="<?= $t->value ?>" class="mt-1 text-amber-500 focus:ring-amber-400"
                           <?= $tipo === $t->value || ($tipo === '' && $i === 0) ? 'checked' : '' ?>>
                    <span>
                        <span class="block font-medium text-black"><?= htmlspecialchars($t->label()) ?></span>
                        <span class="block text-xs text-zinc-500"><?= htmlspecialchars($t->descripcion()) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php $destinoElegido = (string) ($_POST['cliente_destino_id'] ?? ''); ?>
        <label class="block text-sm font-medium text-zinc-700 mb-1" for="cliente-destino">Las cajas nuevas quedan a nombre de</label>
        <select name="cliente_destino_id" id="cliente-destino" class="<?= $claseInput ?> mb-1">
            <option value="">El mismo cliente: <?= htmlspecialchars($etiquetaCliente($cliente)) ?></option>
            <?php foreach ($clientes as $otro): if ($otro->id() === $cliente->id()) { continue; } ?>
                <option value="<?= htmlspecialchars($otro->id()) ?>" <?= $destinoElegido === $otro->id() ? 'selected' : '' ?>><?= htmlspecialchars($etiquetaCliente($otro)) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="text-xs text-zinc-500 mb-4">Si la mercancia sale a nombre de otro cliente, elijalo aqui: <strong>las cargas siguen a nombre de su cliente</strong> (sus facturas no cambian) y solo las cajas nuevas, con las que se arma la salida, quedan a nombre del otro.</p>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Instruccion para bodega (opcional)</label>
        <textarea name="instrucciones" rows="2" maxlength="500" placeholder="Ej: emplasticar bien; separar por marca; no pasar de 1.80 m de alto"
                  class="<?= $claseInput ?>"><?= htmlspecialchars($instrucciones) ?></textarea>
    </div>

    <div class="sticky bottom-0 z-20 bg-white rounded-xl border border-slate-200 shadow-[0_-4px_12px_rgba(0,0,0,0.08)]">
        <div class="px-6 py-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-zinc-600"><strong id="tot-cargas" class="text-black">0</strong> cargas · <strong id="tot-bultos" class="text-black">0</strong> bultos para reempacar</p>
            <button type="submit" id="reempaque-enviar" disabled
                    class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition disabled:opacity-40 disabled:cursor-not-allowed">
                <?= icon_small('check') ?><span>Enviar orden a bodega</span>
            </button>
        </div>
    </div>
</form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Buscar en la lista de clientes (elegir uno recarga la pagina con sus cargas).
    var buscador = document.getElementById('reempaque-cliente-buscar');
    if (buscador) {
        buscador.addEventListener('input', function () {
            var q = buscador.value.trim().toLowerCase();
            document.querySelectorAll('[data-cliente-fila]').forEach(function (fila) {
                fila.classList.toggle('hidden', q !== '' && fila.getAttribute('data-buscar').indexOf(q) === -1);
            });
        });
        buscador.focus();
    }

    var tbody = document.getElementById('reempaque-cargas');
    if (!tbody) {
        return;
    }

    function totales() {
        var marcadas = tbody.querySelectorAll('.reempaque-check:checked');
        var bultos = 0;
        marcadas.forEach(function (c) { bultos += Number(c.dataset.bultos); });
        document.getElementById('tot-cargas').textContent = marcadas.length;
        document.getElementById('tot-bultos').textContent = bultos;
        document.getElementById('reempaque-enviar').disabled = marcadas.length === 0;
        tbody.querySelectorAll('tr').forEach(function (fila) {
            fila.classList.toggle('bg-amber-100', fila.querySelector('.reempaque-check').checked);
        });
    }

    tbody.addEventListener('click', function (evento) {
        var fila = evento.target.closest('tr');
        var check = fila ? fila.querySelector('.reempaque-check') : null;
        if (check && !check.disabled && evento.target !== check) {
            check.checked = !check.checked;
        }
        totales();
    });

    totales();
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
