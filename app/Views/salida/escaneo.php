<?php
use App\Libraries\Csrf;
/** @var \Courier\Salida\Domain\SalidaOrden $orden */
/** @var string $clienteNombre uno o varios clientes (salida consolidada) */
/** @var array<int, array{cargaId: string, tracking: string, cliente: string, marca: ?string, total: int, escaneadas: int, pendientes: array<int, string>, codigos: array<int, string>}> $avance */
/** @var array<int, \Courier\Naviera\Domain\Naviera> $navieras activas (+ la de la orden si esta inactiva) */
/** @var string|null $mensaje */
/** @var string|null $error */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Salida ' . $orden->etiquetaDo();
$pageSubtitle = (str_contains($clienteNombre, ', ') ? 'Clientes: ' : 'Cliente: ') . $clienteNombre;
$backHref = '/salidas/historial';
// Bodega escanea desde la PDA mientras trafico mira: la pantalla revisa cambios cada 8 s (vivo.js).
$vivoSegundos = 8;
// Bodega escanea y finaliza; agregar cargas, cancelar y el embarque los decide trafico/gerencia.
$planifica = in_array($_SESSION['usuario_rol'] ?? null, ['operaciones', 'gerente', 'admin'], true);
$headerActions = ($planifica && $orden->estado()->value === 'pendiente' ? header_button('/salidas/' . $orden->id() . '/agregar', 'plus', 'Agregar cargas', 'emerald') : '')
    // Lo que trafico imprime y entrega a bodega: las etiquetas exactas que suben al contenedor.
    . header_button('/salidas/' . $orden->id() . '/orden-carga', 'printer', 'Orden de carga (bodega)', 'emerald')
    . header_button('/salidas/' . $orden->id() . '/reporte', 'chart', 'Reporte de salida', 'amber')
    . header_button('/salidas/' . $orden->id() . '/manifiesto', 'printer', 'Imprimir manifiesto', 'blue');
require __DIR__ . '/../partials/header.php';

$progreso = $orden->progreso();
$lineasPorCodigo = [];
foreach ($orden->lineas() as $l) {
    $lineasPorCodigo[$l->codigoLpn()] = $l;
}
$confirmada = $orden->estado()->value === 'confirmada';
$cancelada = $orden->estado()->value === 'cancelada';
$embarque = $orden->embarque();
$vivoDo = 'salida:' . $orden->id();
$claseInput = 'w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none disabled:bg-slate-100 disabled:text-zinc-500';
?>

<?php if (!empty($mensaje)): ?>
    <div class="bg-emerald-500/10 text-emerald-700 text-sm rounded-lg px-4 py-3 mb-4 border border-emerald-500/30"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/embarque" id="salida-embarque" data-vivo-region="<?= htmlspecialchars($vivoDo) ?>" class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <?= card_header('truck', 'Datos del embarque', 'sky') ?>
        <span class="text-sm text-zinc-500">Consecutivo: <strong class="text-black"><?= htmlspecialchars($orden->etiquetaDo()) ?></strong></span>
    </div>
    <fieldset <?= $cancelada || !$planifica ? 'disabled' : '' ?> class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Numero de contenedor</label>
            <input type="text" name="numero_contenedor" maxlength="15" placeholder="Ej: TGBU10548-6" value="<?= htmlspecialchars($embarque->contenedorFormateado() ?? '') ?>" class="<?= $claseInput ?> uppercase">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Numero de sello</label>
            <input type="text" name="numero_sello" maxlength="40" placeholder="Ej: SML G4187571" value="<?= htmlspecialchars($embarque->numeroSello ?? '') ?>" class="<?= $claseInput ?> uppercase">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Naviera</label>
            <select name="naviera_id" class="<?= $claseInput ?>">
                <option value="">— Seleccione —</option>
                <?php foreach ($navieras as $naviera): ?>
                    <option value="<?= htmlspecialchars($naviera->id()) ?>" <?= $naviera->id() === $embarque->navieraId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($naviera->nombre()) ?><?= $naviera->activo() ? '' : ' (inactiva)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre del barco</label>
            <input type="text" name="nombre_barco" maxlength="120" value="<?= htmlspecialchars($embarque->nombreBarco ?? '') ?>" class="<?= $claseInput ?> uppercase">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Fecha de cargue</label>
            <input type="date" name="fecha_cargue" value="<?= htmlspecialchars($embarque->fechaCargue?->format('Y-m-d') ?? '') ?>" class="<?= $claseInput ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Fecha de salida (ETD)</label>
            <input type="date" name="fecha_salida_etd" value="<?= htmlspecialchars($embarque->fechaSalidaEtd?->format('Y-m-d') ?? '') ?>" class="<?= $claseInput ?>">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-zinc-700 mb-1">Destino final</label>
            <input type="text" name="destino_final" maxlength="190" placeholder="Ej: Caracas, Venezuela" value="<?= htmlspecialchars($embarque->destinoFinal ?? '') ?>" class="<?= $claseInput ?>">
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full inline-flex justify-center items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 font-medium hover:bg-amber-400 transition disabled:opacity-40">
                <?= icon_small('check') ?><span>Guardar datos del embarque</span>
            </button>
        </div>
    </fieldset>
</form>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex items-end gap-4 flex-wrap justify-between">
        <div class="flex items-end gap-4 flex-wrap">
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Escanear codigo de LPN</label>
                <input type="text" id="salida-scan-input" autocomplete="off" <?= $confirmada || $cancelada ? 'disabled' : 'autofocus' ?>
                       placeholder="Escanea o escribe el codigo y presiona Enter"
                       class="w-80 rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
            </div>
            <p id="salida-scan-mensaje" class="text-sm"></p>
        </div>
        <div class="text-right" id="salida-progreso-zona" data-vivo-region="<?= htmlspecialchars($vivoDo) ?>">
            <p class="text-sm text-zinc-500">Progreso</p>
            <p id="salida-progreso" class="text-lg font-bold text-black" data-escaneadas="<?= $progreso['escaneadas'] ?>" data-total="<?= $progreso['total'] ?>">
                <?= $progreso['escaneadas'] ?> de <?= $progreso['total'] ?> escaneados
            </p>
        </div>
    </div>

    <input type="hidden" id="salida-csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" id="salida-orden-id" value="<?= htmlspecialchars($orden->id()) ?>">

    <div id="salida-acciones" data-vivo-region="<?= htmlspecialchars($vivoDo) ?>">
    <?php if (!$confirmada && !$cancelada): ?>
        <div class="mt-4 flex gap-3">
            <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/finalizar">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button type="submit" id="salida-confirmar-btn" <?= $progreso['escaneadas'] < $progreso['total'] ? 'disabled' : '' ?>
                        class="inline-flex items-center gap-2 bg-emerald-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-emerald-400 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <?= icon_small('check') ?><span>Confirmar Salida</span>
                </button>
            </form>
            <?php if ($planifica): ?>
            <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/cancelar"
                  onsubmit="return confirm('¿Cancelar <?= htmlspecialchars($orden->etiquetaDo()) ?>? Se elimina el DO y sus bultos vuelven a quedar disponibles en bodega.');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button type="submit" class="inline-flex items-center gap-2 bg-red-500/10 text-red-600 rounded-lg px-4 py-2 text-sm font-medium hover:bg-red-500/20 transition">
                    <?= icon_small('x') ?><span>Cancelar Orden</span>
                </button>
            </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="mt-4 text-sm font-medium <?= $confirmada ? 'text-emerald-600' : 'text-red-600' ?>">
            Esta orden esta <?= htmlspecialchars($orden->estado()->label()) ?><?= $orden->nota() ? ' (' . htmlspecialchars($orden->nota()) . ')' : '' ?>.
        </p>
    <?php endif; ?>
    </div>
</div>

<?php
// Organizar el contenedor (trafico/gerencia): unir este DO a otro del mismo contenedor.
$otrasSalidas ??= [];
$puedeOrganizar = $planifica && !$confirmada && !$cancelada && $otrasSalidas !== [];
$opcionSalida = static fn (array $s): string => $s['etiqueta']
    . ($s['estado'] === 'confirmada' ? ' (confirmada)' : '')
    . ($s['contenedor'] ? ' · ' . $s['contenedor'] : '')
    . ($s['clientes'] !== '' ? ' · ' . $s['clientes'] : '');
?>
<?php if ($puedeOrganizar): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <?= card_header('truck', 'Mismo contenedor que otro DO', 'violet') ?>
    <p class="text-xs text-zinc-500 -mt-2 mb-4">
        Si esta salida va en el mismo contenedor que otra, unala: pasan todas sus cargas con lo que bodega ya escaneo y este DO queda cancelado.
        Si una carga se unio por error, use <strong>Sacar</strong> en la tabla de abajo.
    </p>
    <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/unir" class="flex flex-wrap items-end gap-3"
          onsubmit="var s=this.destino_id; if(!s.value){return false;} return confirm('¿Unir <?= htmlspecialchars($orden->etiquetaDo()) ?> a ' + s.options[s.selectedIndex].text.split(' · ')[0] + '? Sus cargas (con fotos) pasan al otro y este DO se elimina.');">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <div class="min-w-[22rem]">
            <label class="block text-sm font-medium text-zinc-700 mb-1">Unir <?= htmlspecialchars($orden->etiquetaDo()) ?> a</label>
            <select name="destino_id" class="<?= $claseInput ?>">
                <option value="">— Elija el DO del contenedor —</option>
                <?php foreach ($otrasSalidas as $s): ?>
                    <option value="<?= htmlspecialchars($s['id']) ?>"><?= htmlspecialchars($opcionSalida($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="inline-flex items-center gap-2 bg-violet-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-violet-500 transition">
            <?= icon_small('check') ?><span>Unir</span>
        </button>
    </form>
    <p class="text-xs text-zinc-500 mt-2">A un DO ya confirmado solo se puede unir si todo esta escaneado: esos bultos quedan despachados con el.</p>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h2 class="font-semibold text-black mb-1">Cargas de esta salida <span id="salida-num-cargas" data-vivo-region="<?= htmlspecialchars($vivoDo) ?>" class="text-zinc-400 font-normal">· <?= count($avance) ?></span></h2>
            <p class="text-xs text-zinc-500">Bodega escanea cada bulto al subirlo al contenedor. Las cargas con bultos pendientes aparecen primero. Use <strong>+</strong> para ver los LPN de una carga.</p>
        </div>
        <div class="flex gap-2 text-xs">
            <button type="button" id="salida-expandir-todo" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-zinc-700 hover:bg-slate-100">Ver todos los LPN</button>
            <button type="button" id="salida-contraer-todo" class="rounded-lg border border-zinc-300 px-3 py-1.5 text-zinc-700 hover:bg-slate-100">Ocultar LPN</button>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">Carga / LPN</th>
                    <th class="py-2 pr-4">Cliente</th>
                    <th class="py-2 pr-4">Marca</th>
                    <th class="py-2 text-right">Escaneados</th>
                </tr>
            </thead>
            <tbody id="salida-tbody" data-vivo-region="<?= htmlspecialchars($vivoDo) ?>">
                <?php foreach ($avance as $grupo): $completa = $grupo['escaneadas'] >= $grupo['total']; ?>
                    <tr class="border-b border-slate-200 <?= $completa ? 'bg-emerald-50' : 'bg-slate-50' ?>" data-grupo-carga="<?= htmlspecialchars($grupo['cargaId']) ?>">
                        <td class="py-2 pr-4 font-mono font-semibold text-black">
                            <?php // + / - : muestra u oculta los LPN de esta carga (ocultos al abrir). ?>
                            <button type="button" class="salida-toggle inline-flex items-center justify-center w-6 h-6 mr-2 rounded border border-zinc-300 bg-white text-zinc-700 font-sans font-bold leading-none hover:bg-slate-100 align-middle"
                                    data-toggle-carga="<?= htmlspecialchars($grupo['cargaId']) ?>" aria-expanded="false" title="Ver / ocultar los LPN">+</button>
                            <?php if (!str_starts_with($grupo['cargaId'], 'caja-')): ?>
                                <a href="/cargas/<?= htmlspecialchars($grupo['cargaId']) ?>" class="hover:underline"><?= htmlspecialchars($grupo['tracking']) ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($grupo['tracking']) ?>
                            <?php endif; ?>
                            <?php if (!empty($grupo['factura'])): ?>
                                <?php // Factura del proveedor de la carga: se ve en el visor (modal). ?>
                                <a href="/<?= htmlspecialchars($grupo['factura']['ruta']) ?>" target="_blank" rel="noopener" data-doc-ver
                                   data-doc-titulo="Factura · <?= htmlspecialchars($grupo['tracking'] . ' · ' . $grupo['cliente']) ?>"
                                   title="Ver factura del proveedor"
                                   class="ml-1 inline-flex items-center gap-1 rounded-lg border border-red-300 bg-red-50 px-2 py-0.5 font-sans font-medium text-xs text-red-700 hover:bg-red-100 align-middle">
                                    <?= icon_small('receipt', 'w-3 h-3') ?> Factura
                                </a>
                            <?php elseif (!str_starts_with($grupo['cargaId'], 'caja-')): ?>
                                <span class="ml-1 font-sans font-normal text-xs text-zinc-400 align-middle">Sin factura</span>
                            <?php endif; ?>
                            <?php $quedan = $quedanPorCarga[$grupo['cargaId']] ?? []; if ($quedan !== []): ?>
                                <?php // Carga que sale en parte: cuantos suben aqui y cuantos se quedan (se ven con +). ?>
                                <span class="block mt-1 font-sans font-normal text-xs text-amber-700">
                                    Parcial: <?= $grupo['total'] ?> de <?= $grupo['total'] + count($quedan) ?> suben · <?= count($quedan) ?> se quedan
                                </span>
                            <?php endif; ?>
                            <?php if ($planifica && !$confirmada && !$cancelada && !str_starts_with($grupo['cargaId'], 'caja-')): ?>
                                <?php // Elegir que bultos de esta carga suben a este contenedor (p.ej. 20 de 143); el resto vuelve a bodega. ?>
                                <button type="button" class="salida-ajustar mt-1 inline-flex items-center gap-1 rounded-lg border border-amber-400 bg-amber-50 px-2 py-0.5 font-sans font-medium text-xs text-amber-800 hover:bg-amber-100"
                                        data-carga="<?= htmlspecialchars($grupo['cargaId']) ?>" data-tracking="<?= htmlspecialchars($grupo['tracking']) ?>">
                                    <?= icon_small('pencil', 'w-3 h-3') ?> Ajustar bultos
                                </button>
                            <?php endif; ?>
                            <?php if ($planifica && !$confirmada && !$cancelada): ?>
                                <?php // Sacar la carga de este DO: a otro DO (conserva lo escaneado) o de vuelta a bodega. ?>
                                <details class="mt-1 font-sans font-normal">
                                    <summary class="text-xs text-violet-700 cursor-pointer hover:underline">Sacar</summary>
                                    <div class="mt-2 p-3 rounded-lg border border-slate-200 bg-white space-y-2 w-80">
                                        <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/mover-carga" class="flex gap-2 items-center">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                            <input type="hidden" name="grupo" value="<?= htmlspecialchars($grupo['cargaId']) ?>">
                                            <select name="destino_id" class="flex-1 rounded-lg border border-zinc-300 bg-white text-black px-2 py-1 text-xs">
                                                <option value="nuevo">A un DO nuevo</option>
                                                <?php foreach ($otrasSalidas as $s): if ($s['estado'] !== 'pendiente') { continue; } ?>
                                                    <option value="<?= htmlspecialchars($s['id']) ?>">A <?= htmlspecialchars($opcionSalida($s)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="rounded-lg bg-violet-600 text-white px-3 py-1 text-xs font-medium hover:bg-violet-500">Mover</button>
                                        </form>
                                        <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/devolver-carga"
                                              onsubmit="return confirm('¿Devolver <?= htmlspecialchars($grupo['tracking']) ?> a bodega? Sus <?= $grupo['total'] ?> bultos salen de este DO y quedan disponibles.');">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                            <input type="hidden" name="grupo" value="<?= htmlspecialchars($grupo['cargaId']) ?>">
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Devolver a bodega (sale de este DO)</button>
                                        </form>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 pr-4 text-black"><?= htmlspecialchars($grupo['cliente']) ?></td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($grupo['marca'] ?? '—') ?></td>
                        <td class="py-2 text-right font-semibold">
                            <span class="salida-carga-contador" data-carga="<?= htmlspecialchars($grupo['cargaId']) ?>" data-total="<?= $grupo['total'] ?>"><?= $grupo['escaneadas'] ?> / <?= $grupo['total'] ?></span>
                        </td>
                    </tr>
                    <?php foreach ($grupo['codigos'] as $codigo): $linea = $lineasPorCodigo[$codigo] ?? null; if ($linea === null) { continue; } ?>
                        <tr class="hidden border-b border-slate-100" data-codigo="<?= htmlspecialchars($linea->codigoLpn()) ?>" data-carga="<?= htmlspecialchars($grupo['cargaId']) ?>" data-escaneado="<?= $linea->escaneado() ? '1' : '0' ?>">
                            <td class="py-1.5 pr-4 pl-6 font-mono text-zinc-700"><?= htmlspecialchars($linea->codigoLpn()) ?></td>
                            <td class="py-1.5 pr-4" colspan="2"></td>
                            <td class="py-1.5 text-right salida-linea-estado">
                                <?= $linea->escaneado() ? badge('Escaneado', 'emerald') : badge('Pendiente', 'slate') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($quedanPorCarga[$grupo['cargaId']] ?? [] as $q): ?>
                        <?php // Bultos de la carga que NO van en esta salida (sin data-codigo: no cuentan para el escaneo). ?>
                        <tr class="hidden border-b border-slate-100 bg-slate-50 text-zinc-400" data-carga="<?= htmlspecialchars($grupo['cargaId']) ?>" data-queda="1">
                            <td class="py-1.5 pr-4 pl-6 font-mono line-through"><?= htmlspecialchars($q['codigo']) ?></td>
                            <td class="py-1.5 pr-4 text-xs" colspan="2"><?= htmlspecialchars($q['otraSalida'] ? 'Va en ' . $q['otraSalida'] : ($q['estado'] === 'receiving' ? 'Sin escanear en recepcion' : ($q['ubicacion'] ?? 'Bodega general'))) ?></td>
                            <td class="py-1.5 text-right"><?= badge('Se queda', 'amber') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($planifica && !$confirmada && !$cancelada): ?>
<!-- Ajustar bultos: trafico deja marcados solo los bultos de la carga que suben a este contenedor -->
<div id="modal-ajustar" class="hidden fixed inset-0 bg-black/60 z-50 items-center justify-center p-4">
    <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/ajustar-carga" class="bg-white rounded-xl shadow-xl max-w-xl w-full flex flex-col max-h-[88vh]">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="carga_id" id="ajustar-carga-id">
        <div class="p-5 border-b border-slate-200">
            <h2 class="font-semibold text-black text-lg">Ajustar bultos · <span id="ajustar-titulo" class="font-mono"></span></h2>
            <p class="text-xs text-zinc-500 mt-1">Deje marcados <strong>solo los bultos que suben a este contenedor</strong>. Los que desmarque vuelven a bodega (disponibles para otro contenedor). Lo ya escaneado se conserva.</p>
            <div class="flex flex-wrap items-center gap-2 mt-3 text-xs">
                <span>Marcar los primeros</span>
                <input type="number" id="ajustar-primeros" min="1" class="w-20 rounded-lg border border-zinc-300 px-2 py-1 text-sm">
                <button type="button" id="ajustar-aplicar-primeros" class="rounded-lg border border-zinc-300 px-2 py-1 hover:bg-slate-100">Aplicar</button>
                <span class="text-zinc-300">|</span>
                <button type="button" id="ajustar-todos" class="rounded-lg border border-zinc-300 px-2 py-1 hover:bg-slate-100">Todos</button>
                <button type="button" id="ajustar-ninguno" class="rounded-lg border border-zinc-300 px-2 py-1 hover:bg-slate-100">Ninguno</button>
                <input type="search" id="ajustar-buscar" placeholder="Buscar..." class="flex-1 min-w-[8rem] rounded-lg border border-zinc-300 px-2 py-1 text-sm">
            </div>
        </div>
        <div id="ajustar-lista" class="overflow-y-auto flex-1"></div>
        <div class="p-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <span class="text-sm text-zinc-700">Suben <strong id="ajustar-suben" class="text-black">0</strong> de <span id="ajustar-total">0</span> · <span id="ajustar-quedan" class="text-amber-700"></span></span>
            <div class="flex gap-2">
                <button type="button" data-cerrar-ajuste class="bg-slate-200 text-zinc-800 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-300">Cancelar</button>
                <button type="submit" class="bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400">Guardar</button>
            </div>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('modal-ajustar');
    var lista = document.getElementById('ajustar-lista');

    function checks() { return Array.prototype.slice.call(lista.querySelectorAll('input[type=checkbox]:not(:disabled)')); }

    function contar() {
        var marcados = checks().filter(function (c) { return c.checked; }).length;
        var total = lista.querySelectorAll('input[type=checkbox]').length;
        document.getElementById('ajustar-suben').textContent = marcados;
        document.getElementById('ajustar-total').textContent = total;
        document.getElementById('ajustar-quedan').textContent = (total - marcados) + ' se quedan';
    }

    // Delegado: la tabla se reemplaza cuando hay cambios (vivo.js) y los botones son nuevos.
    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('.salida-ajustar');

        if (!boton) {
            return;
        }

        (function () {
            var carga = boton.getAttribute('data-carga');
            document.getElementById('ajustar-carga-id').value = carga;
            document.getElementById('ajustar-titulo').textContent = boton.getAttribute('data-tracking');
            document.getElementById('ajustar-buscar').value = '';
            lista.innerHTML = '<p class="text-sm text-zinc-500 p-3">Cargando...</p>';
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            fetch('/salidas/<?= htmlspecialchars($orden->id()) ?>/carga-bultos/' + encodeURIComponent(carga), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (datos) {
                    lista.innerHTML = datos.bultos.map(function (b) {
                        var estado = b.enEsteDo ? (b.escaneado ? 'Ya cargado' : 'En este DO') : (b.disponible ? 'En bodega' : b.motivo);
                        return '<label class="flex items-center gap-3 px-3 py-2 border-b border-slate-100 ' + (b.disponible ? 'hover:bg-amber-50 cursor-pointer' : 'opacity-50') + '" data-buscar="' + b.codigo.toLowerCase() + '">'
                            + '<input type="checkbox" name="lpns[]" value="' + b.id + '" ' + (b.enEsteDo ? 'checked' : '') + ' ' + (b.disponible ? '' : 'disabled') + ' class="rounded border-zinc-300 text-amber-500 focus:ring-amber-400">'
                            + '<span class="font-mono text-sm text-black flex-1">' + b.codigo + '</span>'
                            + '<span class="text-xs text-zinc-500 w-16 text-right">' + (b.numero ? b.numero + '/' + b.total : '') + '</span>'
                            + '<span class="text-xs w-32 text-right ' + (b.escaneado ? 'text-emerald-700' : 'text-zinc-500') + '">' + estado + '</span>'
                            + '</label>';
                    }).join('');
                    contar();
                }, function () {
                    lista.innerHTML = '<p class="text-sm text-red-600 p-3">No se pudo cargar la lista. Intente de nuevo.</p>';
                });
        })();
    });

    lista.addEventListener('change', contar);
    document.getElementById('ajustar-todos').addEventListener('click', function () { checks().forEach(function (c) { c.checked = true; }); contar(); });
    document.getElementById('ajustar-ninguno').addEventListener('click', function () { checks().forEach(function (c) { c.checked = false; }); contar(); });
    document.getElementById('ajustar-aplicar-primeros').addEventListener('click', function () {
        var n = parseInt(document.getElementById('ajustar-primeros').value, 10) || 0;
        checks().forEach(function (c, i) { c.checked = i < n; });
        contar();
    });
    document.getElementById('ajustar-buscar').addEventListener('input', function () {
        var texto = this.value.trim().toLowerCase();
        lista.querySelectorAll('label').forEach(function (l) { l.classList.toggle('hidden', texto !== '' && l.getAttribute('data-buscar').indexOf(texto) === -1); });
    });
    modal.querySelector('[data-cerrar-ajuste]').addEventListener('click', function () { modal.classList.add('hidden'); modal.classList.remove('flex'); });
    modal.querySelector('form').addEventListener('submit', function (e) {
        var marcados = checks().filter(function (c) { return c.checked; }).length;
        if (marcados === 0) { e.preventDefault(); alert('Deje marcado al menos un bulto. Para sacar toda la carga use "Sacar > Devolver a bodega".'); return; }
        if (!confirm('¿Guardar? Suben ' + marcados + ' bultos de esta carga en este contenedor; los demas quedan en bodega.')) { e.preventDefault(); }
    });
});
</script>
<?php endif; ?>

<script>
// LPN de cada carga plegados: + los muestra, - los oculta (las filas siguen en la pagina para el escaneo).
document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.getElementById('salida-tbody');

    if (!tbody) {
        return;
    }

    function mostrar(boton, abrir) {
        var carga = boton.getAttribute('data-toggle-carga');
        tbody.querySelectorAll('tr[data-carga="' + carga + '"]').forEach(function (fila) {
            fila.classList.toggle('hidden', !abrir);
        });
        boton.textContent = abrir ? '−' : '+';
        boton.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    }

    tbody.addEventListener('click', function (evento) {
        var boton = evento.target.closest('.salida-toggle');

        if (boton) {
            mostrar(boton, boton.getAttribute('aria-expanded') !== 'true');
        }
    });

    document.getElementById('salida-expandir-todo').addEventListener('click', function () {
        tbody.querySelectorAll('.salida-toggle').forEach(function (b) { mostrar(b, true); });
    });
    document.getElementById('salida-contraer-todo').addEventListener('click', function () {
        tbody.querySelectorAll('.salida-toggle').forEach(function (b) { mostrar(b, false); });
    });

    // Actualizacion automatica (vivo.js): las cargas que estaban abiertas siguen abiertas.
    var abiertas = [];
    document.addEventListener('vivo:antes', function (e) {
        if (e.detail.region === tbody) {
            abiertas = Array.prototype.map.call(tbody.querySelectorAll('.salida-toggle[aria-expanded="true"]'), function (b) {
                return b.getAttribute('data-toggle-carga');
            });
        }
    });
    document.addEventListener('vivo:actualizado', function (e) {
        if (e.detail.region === tbody) {
            tbody.querySelectorAll('.salida-toggle').forEach(function (b) {
                if (abiertas.indexOf(b.getAttribute('data-toggle-carga')) !== -1) { mostrar(b, true); }
            });
        }

        // Si otro usuario confirmo o cancelo el DO, ya no se escanea aqui.
        var scan = document.getElementById('salida-scan-input');
        if (e.detail.region.id === 'salida-acciones' && scan && !document.getElementById('salida-confirmar-btn')) {
            scan.disabled = true;
        }
    });
});
</script>

<?php
// Fotos por carga del cliente (sus bultos/paletas desde varios angulos), no por etiqueta.
$fotosTitulo = 'Fotos de la salida';
$fotosAyuda = 'Una galeria por carga: bodega fotografia la carga de cada cliente al subirla al contenedor (app movil); tambien se pueden agregar aqui.';
$fotosPorCarga = [];
foreach ($fotos as $foto) {
    $fotosPorCarga[$foto['cargaId'] ?? ''][] = $foto;
}
$fotosGrupos = [];
foreach ($avance as $grupo) {
    if (str_starts_with($grupo['cargaId'], 'caja-')) {
        continue;
    }
    $fotosGrupos[] = [
        'titulo' => $grupo['tracking'] . ' · ' . $grupo['cliente'],
        'subtitulo' => ($grupo['marca'] ? 'Marca ' . $grupo['marca'] . ' · ' : '') . $grupo['total'] . ' bultos',
        'urlSubir' => $cancelada ? null : '/bodega/api/salidas/' . $orden->id() . '/fotos?carga=' . $grupo['cargaId'],
        'fotos' => $fotosPorCarga[$grupo['cargaId']] ?? [],
    ];
}
if (!empty($fotosPorCarga[''])) {
    $fotosGrupos[] = ['titulo' => 'Sin carga asignada', 'subtitulo' => null, 'urlSubir' => null, 'fotos' => $fotosPorCarga['']];
}
$fotosVolver = '/salidas/' . $orden->id();
require __DIR__ . '/../evidencia/_fotos.php';
?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
