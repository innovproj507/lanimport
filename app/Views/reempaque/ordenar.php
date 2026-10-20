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

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <?= card_header('users', '1. Cliente', 'amber') ?>
    <p class="text-xs text-zinc-500 mb-2">Un reempaque es siempre de un solo cliente.</p>
    <input type="text" id="reempaque-cliente" list="reempaque-clientes" autocomplete="off"
           placeholder="Escriba el codigo, nombre o marca del cliente..."
           value="<?= $cliente !== null ? htmlspecialchars($etiquetaCliente($cliente)) : '' ?>" class="<?= $claseInput ?> max-w-2xl">
    <datalist id="reempaque-clientes">
        <?php foreach ($clientes as $c): if (!$c->activo()) { continue; } ?>
            <option value="<?= htmlspecialchars($etiquetaCliente($c)) ?>" data-id="<?= htmlspecialchars($c->id()) ?>"></option>
        <?php endforeach; ?>
    </datalist>
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
        <?= card_header('pencil', '3. Que debe hacer bodega', 'amber') ?>
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
        <label class="block text-sm font-medium text-zinc-700 mb-1">Instruccion para bodega (opcional)</label>
        <textarea name="instrucciones" rows="2" maxlength="500" placeholder="Ej: armar 1 paleta con todo; o 2 cajas, una por proveedor"
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
    // Elegir el cliente recarga la pagina con sus cargas.
    var buscador = document.getElementById('reempaque-cliente');
    buscador.addEventListener('change', function () {
        var opcion = [].find.call(document.querySelectorAll('#reempaque-clientes option'), function (o) { return o.value === buscador.value; });
        if (opcion) {
            location.href = '/reempaques?cliente_id=' + encodeURIComponent(opcion.dataset.id);
        }
    });

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
