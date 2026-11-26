<?php
use App\Libraries\Csrf;
/** @var \Courier\Reempaque\Domain\ReempaqueOrden $orden */
/** @var \Courier\Cliente\Domain\Cliente|null $cliente */
/** @var array<string, \Courier\Lpn\Domain\Lpn|null> $lpnsPorId origenes y cajas */
/** @var string|null $mensaje */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Reempaque ' . ($orden->codigo() ?? '');
$pageSubtitle = ($cliente?->nombreCompleto() ?? '—') . ' · ' . $orden->tipo()->label();
$backHref = '/reempaques/historial';
require __DIR__ . '/../partials/header.php';

$pendiente = $orden->estado()->value === 'pendiente';
$progreso = $orden->progreso();
$sinEmpacar = $orden->origenesSinEmpacar();
$puedeOrdenar = in_array($_SESSION['usuario_rol'] ?? '', ['operaciones', 'gerente', 'admin'], true);
$codigo = static fn (string $id): string => (string) ($lpnsPorId[$id]?->codigo() ?? '—');
$estadoColores = ['pendiente' => 'amber', 'completado' => 'emerald', 'cancelado' => 'slate'];

// Bultos pendientes agrupados por la marca/carga de origen (lo que bodega busca en el piso).
$pendientesPorMarca = [];
foreach ($sinEmpacar as $id) {
    $pendientesPorMarca[$lpnsPorId[$id]?->marca() ?? 'Sin marca'][] = $codigo($id);
}
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($mensaje)): ?>
    <div class="bg-emerald-500/10 text-emerald-700 text-sm rounded-lg px-4 py-3 mb-4 border border-emerald-500/30"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-6 lg:col-span-2">
        <div class="flex items-start justify-between gap-4 mb-3">
            <?= card_header('box', 'Orden para bodega', 'violet') ?>
            <?= badge($orden->estado()->label(), $estadoColores[$orden->estado()->value] ?? 'slate') ?>
        </div>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div><dt class="text-zinc-500">Cliente</dt><dd class="text-black font-medium"><?= htmlspecialchars($cliente?->nombreCompleto() ?? '—') ?></dd></div>
            <div class="sm:col-span-2">
                <dt class="text-zinc-500">Cajas nuevas a nombre de</dt>
                <dd class="<?= !empty($clienteCajas) ? 'text-orange-700 font-semibold' : 'text-black' ?>">
                    <?= htmlspecialchars(!empty($clienteCajas) ? $clienteCajas->nombreCompleto() : ($cliente?->nombreCompleto() ?? '—') . ' (el mismo de las cargas)') ?>
                </dd>
                <?php if ($puedeOrdenar && $orden->estado()->value !== 'cancelado'): ?>
                    <?php $etqCli = static fn ($c): string => trim(($c->codigo() ? $c->codigo() . ' — ' : '') . $c->nombreCompleto() . ($c->empresa() ? ' — ' . $c->empresa() : '')); ?>
                    <details class="mt-2" <?= !empty($error) ? 'open' : '' ?>>
                        <summary class="cursor-pointer text-sm text-blue-700 underline select-none">Cambiar cliente de las cajas</summary>
                        <form method="post" action="/reempaques/<?= htmlspecialchars($orden->id()) ?>/cliente-cajas" class="mt-2 flex flex-wrap items-center gap-2"
                              onsubmit="return confirm('Las cajas de este reempaque quedaran a nombre del cliente elegido. Las cargas siguen a nombre de su cliente. ¿Continuar?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <select name="cliente_cajas_id" required class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm min-w-[18rem] focus:ring-2 focus:ring-amber-400 focus:outline-none">
                                <?php foreach ($clientes as $cl): ?>
                                    <option value="<?= htmlspecialchars($cl->id()) ?>" <?= $cl->id() === $orden->clienteCajas() ? 'selected' : '' ?>><?= htmlspecialchars($etqCli($cl)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400">Guardar</button>
                        </form>
                        <p class="text-xs text-zinc-500 mt-1">Las cargas no cambian (sus facturas siguen igual). Solo las cajas, con las que se arma la salida. No se puede si la caja ya esta en una OS/DO.</p>
                    </details>
                <?php endif; ?>
            </div>
            <div><dt class="text-zinc-500">Tipo</dt><dd class="text-black font-medium"><?= htmlspecialchars($orden->tipo()->label()) ?></dd></div>
            <div class="sm:col-span-2"><dt class="text-zinc-500">Instruccion</dt><dd class="text-black"><?= htmlspecialchars($orden->instrucciones() ?? 'Sin instruccion') ?></dd></div>
            <div><dt class="text-zinc-500">Ordenada</dt><dd class="text-black"><?= htmlspecialchars($orden->createdAt()->format('d/m/Y H:i')) ?></dd></div>
            <div><dt class="text-zinc-500">Terminada</dt><dd class="text-black"><?= htmlspecialchars($orden->completedAt()?->format('d/m/Y H:i') ?? '—') ?></dd></div>
        </dl>
        <?php if ($pendiente): ?>
            <p class="text-xs text-zinc-500 mt-4">Bodega la ejecuta en la app de bodega (PDA): Reempaque › esta orden › abrir caja, escanear, cerrar caja, terminar.</p>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="font-semibold text-black mb-3">Avance</h2>
        <p class="text-3xl font-bold text-black"><?= $progreso['empacados'] ?> <span class="text-base font-medium text-zinc-500">de <?= $progreso['total'] ?> bultos</span></p>
        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden my-3">
            <div class="h-2.5 bg-violet-500" style="width: <?= $progreso['total'] > 0 ? (int) round($progreso['empacados'] * 100 / $progreso['total']) : 0 ?>%"></div>
        </div>
        <p class="text-sm text-zinc-600"><?= $progreso['cajas'] ?> caja(s) cerrada(s)<?= $orden->cajaAbierta() !== null ? ' · 1 abierta' : '' ?></p>
        <?php if ($pendiente && $puedeOrdenar): ?>
            <form method="post" action="/reempaques/<?= htmlspecialchars($orden->id()) ?>/cancelar" class="mt-4"
                  onsubmit="return confirm('¿Cancelar la orden? Los bultos vuelven a quedar libres y las cajas nuevas se descartan.');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button type="submit" class="inline-flex items-center gap-2 bg-red-500/10 text-red-600 rounded-lg px-4 py-2 text-sm font-medium hover:bg-red-500/20 transition">
                    <?= icon_small('x') ?><span>Cancelar orden</span>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="font-semibold text-black mb-1">Cajas y paletas nuevas</h2>
    <p class="text-xs text-zinc-500 mb-4">Al cerrar una caja en la PDA se genera su etiqueta: imprimala aqui y peguela en la caja.</p>
    <?php if ($orden->cajas() === []): ?>
        <p class="text-sm text-zinc-500">Bodega aun no ha abierto ninguna caja.</p>
    <?php else: ?>
        <div class="space-y-3">
            <?php $numero = 0; foreach ($orden->cajas() as $cajaId => $caja): $numero++; $codigoCaja = $codigo($cajaId); ?>
                <div class="rounded-lg border <?= $caja['cerrada'] ? 'border-slate-200' : 'border-amber-300 bg-amber-50' ?> p-4 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono font-semibold text-black"><?= htmlspecialchars($codigoCaja) ?>
                            <span class="ml-2"><?= $caja['cerrada'] ? badge('Cerrada', 'emerald') : badge('Abierta en la PDA', 'amber') ?></span>
                        </p>
                        <p class="text-sm text-zinc-600 mt-1">Caja <?= $numero ?> · <?= count($caja['contenido']) ?> bulto(s):
                            <span class="font-mono text-xs"><?= htmlspecialchars(implode(', ', array_map($codigo, $caja['contenido']))) ?></span>
                        </p>
                    </div>
                    <?php if ($caja['cerrada']): ?>
                        <div class="flex items-center gap-2">
                            <a href="<?= htmlspecialchars(url_archivo_publico('uploads/lpns/' . $codigoCaja . '.png')) ?>" data-doc-ver
                               data-doc-titulo="Etiqueta · <?= htmlspecialchars($codigoCaja) ?>" title="Ver etiqueta en grande" class="cursor-zoom-in">
                                <img src="<?= htmlspecialchars(url_archivo_publico('uploads/lpns/' . $codigoCaja . '.png')) ?>" alt="Etiqueta" class="h-12 border border-slate-200 rounded hover:ring-2 hover:ring-amber-400">
                            </a>
                            <?php if (puede_imprimir()): ?>
                            <button type="button" class="reempaque-imprimir inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-100 transition"
                                    data-etiqueta="<?= htmlspecialchars(url_archivo_publico('uploads/lpns/' . $codigoCaja . '.png')) ?>">
                                <?= icon_small('printer') ?><span>Imprimir etiqueta</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// Nuevo ingreso: las cajas pasan a una carga nueva (tracking nuevo) con sus propias medidas.
$puedeIngreso = in_array($_SESSION['usuario_rol'] ?? '', ['inventario', 'operaciones', 'gerente', 'admin'], true);
$formIngreso = $_SESSION['reempaque_ingreso_form'] ?? [];
unset($_SESSION['reempaque_ingreso_form']);
$valorForm = static fn (string $cajaId, string $campo): string => htmlspecialchars((string) ($formIngreso['caja'][$cajaId][$campo] ?? ''));
?>
<?php if (!empty($ingreso['generada'])): $nueva = $ingreso['generada']; ?>
    <div id="nuevo-ingreso" class="bg-violet-50 border border-violet-200 rounded-xl p-5 mb-6 flex flex-wrap items-center gap-3">
        <?= icon_small('box', 'w-5 h-5 text-violet-700') ?>
        <p class="text-sm text-violet-900">Las cajas de este reempaque pasaron al nuevo ingreso
            <a href="/cargas/<?= htmlspecialchars($nueva->id()) ?>" class="font-semibold underline"><?= htmlspecialchars((string) $nueva->trackingNumero()) ?></a>
            (<?= htmlspecialchars($nueva->estado()->label()) ?>), con sus propias medidas.</p>
    </div>
<?php elseif ($orden->estado()->value === 'completado' && $puedeIngreso && $orden->cajas() !== []): ?>
    <form id="nuevo-ingreso" method="post" action="/reempaques/<?= htmlspecialchars($orden->id()) ?>/nuevo-ingreso" class="bg-white rounded-xl shadow-sm p-6 mb-6 space-y-4"
          onsubmit="return confirm('Se creara un nuevo ingreso (tracking nuevo) con estas cajas y sus medidas. Luego se imprimen sus etiquetas y bodega las escanea en Recepcion. ¿Continuar?');">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <div>
            <h2 class="font-semibold text-black">Nuevo ingreso con las medidas de las cajas</h2>
            <p class="text-xs text-zinc-500 mt-1">Para enviar las cajas como una carga nueva (p.ej. aereo, sin paleta): se crea un tracking nuevo a nombre de
                <strong><?= htmlspecialchars(!empty($clienteCajas) ? $clienteCajas->nombreCompleto() : ($cliente?->nombreCompleto() ?? '—')) ?></strong>, una linea por caja con su ancho, alto, largo, peso y P3.
                Las cargas originales que queden sin bultos pasan a "Reempacada" y la salida se arma con la carga nueva.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <?php foreach (\Courier\Carga\Domain\TipoEnvio::cases() as $te): $marcado = ($formIngreso['tipo_envio'] ?? $ingreso['tipoEnvio']) === $te->value; ?>
                <label class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm cursor-pointer has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                    <input type="radio" name="tipo_envio" value="<?= $te->value ?>" <?= $marcado ? 'checked' : '' ?> required> <?= $te->icono() ?> <?= htmlspecialchars($te->label()) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <label class="block md:col-span-2">
                <span class="block text-sm font-medium text-zinc-700 mb-1">Descripcion de la mercancia</span>
                <input type="text" name="descripcion" required maxlength="190" value="<?= htmlspecialchars($formIngreso['descripcion'] ?? $ingreso['descripcion']) ?>"
                       class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
            </label>
            <label class="block">
                <span class="block text-sm font-medium text-zinc-700 mb-1">Valor total</span>
                <input type="number" name="valor" min="0" step="0.01" required value="<?= htmlspecialchars((string) ($formIngreso['valor'] ?? number_format($ingreso['valor'], 2, '.', ''))) ?>"
                       class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm text-right focus:ring-2 focus:ring-amber-400 focus:outline-none">
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 pr-3">Caja</th>
                        <th class="py-2 pr-3 text-right">Ancho (W) cm</th>
                        <th class="py-2 pr-3 text-right">Alto (H) cm</th>
                        <th class="py-2 pr-3 text-right">Largo (L) cm</th>
                        <th class="py-2 pr-3 text-right">Peso kg</th>
                        <th class="py-2 text-right">P3</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orden->cajas() as $cajaId => $caja): $cId = htmlspecialchars($cajaId); ?>
                        <tr class="border-b border-slate-100 ingreso-caja">
                            <td class="py-2 pr-3"><span class="font-mono font-semibold"><?= htmlspecialchars($codigo($cajaId)) ?></span>
                                <span class="block text-xs text-zinc-500"><?= count($caja['contenido']) ?> bulto(s)</span></td>
                            <?php foreach (['ancho', 'alto', 'largo', 'peso'] as $campo): ?>
                                <td class="py-2 pr-3 text-right"><input type="number" name="caja[<?= $cId ?>][<?= $campo ?>]" min="0.01" step="0.01" required value="<?= $valorForm($cajaId, $campo) ?>"
                                       data-medida="<?= $campo ?>" class="w-24 rounded-lg border border-zinc-300 px-2 py-1 text-right"></td>
                            <?php endforeach; ?>
                            <td class="py-2 text-right"><input type="number" name="caja[<?= $cId ?>][p3]" min="0" step="0.0001" value="<?= $valorForm($cajaId, 'p3') ?>" data-medida="p3"
                                   placeholder="auto" class="w-24 rounded-lg border border-zinc-300 px-2 py-1 text-right"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="font-semibold text-black">
                        <td class="py-2 pr-3">Total</td><td colspan="3"></td>
                        <td class="py-2 pr-3 text-right" id="ingreso-total-peso">0</td>
                        <td class="py-2 text-right" id="ingreso-total-p3">0</td>
                    </tr>
                </tfoot>
            </table>
            <p class="text-xs text-zinc-500 mt-1">El P3 se calcula solo (W x H x L / 28,316.85) mientras no se escriba a mano.</p>
        </div>

        <label class="block">
            <span class="block text-sm font-medium text-zinc-700 mb-1">Notas (opcional)</span>
            <textarea name="notas" rows="2" maxlength="1000" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none"><?= htmlspecialchars($formIngreso['notas'] ?? '') ?></textarea>
        </label>

        <button type="submit" class="inline-flex items-center gap-2 bg-violet-600 text-white rounded-lg px-5 py-2.5 text-sm font-medium hover:bg-violet-500"><?= icon_small('check') ?>Generar nuevo ingreso</button>
    </form>
    <script>
    (function () {
        var form = document.getElementById('nuevo-ingreso');
        function recalcular() {
            var peso = 0, p3 = 0;
            form.querySelectorAll('.ingreso-caja').forEach(function (tr) {
                var v = function (m) { return parseFloat(tr.querySelector('[data-medida="' + m + '"]').value) || 0; };
                var campoP3 = tr.querySelector('[data-medida="p3"]');
                var auto = v('ancho') * v('alto') * v('largo') / 28316.846592;
                if (!campoP3.dataset.manual) { campoP3.value = auto > 0 ? auto.toFixed(4) : ''; }
                peso += v('peso');
                p3 += parseFloat(campoP3.value) || 0;
            });
            document.getElementById('ingreso-total-peso').textContent = peso.toFixed(2);
            document.getElementById('ingreso-total-p3').textContent = p3.toFixed(4);
        }
        form.addEventListener('input', function (e) {
            if (e.target.getAttribute('data-medida') === 'p3') { e.target.dataset.manual = e.target.value !== '' ? '1' : ''; }
            recalcular();
        });
        form.querySelectorAll('[data-medida="p3"]').forEach(function (c) { if (c.value !== '') { c.dataset.manual = '1'; } });
        recalcular();
    })();
    </script>
<?php endif; ?>

<?php if ($pendiente && $pendientesPorMarca !== []): ?>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="font-semibold text-black mb-3">Bultos por empacar (<?= count($sinEmpacar) ?>)</h2>
        <?php foreach ($pendientesPorMarca as $marca => $codigos): ?>
            <p class="text-sm text-zinc-700 mb-1"><strong class="text-black"><?= htmlspecialchars((string) $marca) ?>:</strong>
                <span class="font-mono text-xs"><?= htmlspecialchars(implode(', ', $codigos)) ?></span></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script src="/assets/js/local-print.js"></script>
<script>
document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('.reempaque-imprimir');
    if (!boton) {
        return;
    }
    var impresora = LocalPrint.getImpresoraEtiquetas();
    if (!impresora) {
        alert('Configure la impresora de etiquetas en Impresoras.');
        return;
    }
    LocalPrint.imprimirImagen(impresora, window.location.origin + boton.dataset.etiqueta).catch(function (err) {
        alert(err && err.message ? err.message : String(err));
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
