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
// Bodega escanea y finaliza; agregar cargas, cancelar y el embarque los decide trafico/gerencia.
$planifica = in_array($_SESSION['usuario_rol'] ?? null, ['operaciones', 'gerente', 'admin'], true);
$headerActions = ($planifica && $orden->estado()->value === 'pendiente' ? header_button('/salidas/' . $orden->id() . '/agregar', 'plus', 'Agregar cargas', 'emerald') : '')
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
$claseInput = 'w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none disabled:bg-slate-100 disabled:text-zinc-500';
?>

<?php if (!empty($mensaje)): ?>
    <div class="bg-emerald-500/10 text-emerald-700 text-sm rounded-lg px-4 py-3 mb-4 border border-emerald-500/30"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/embarque" class="bg-white rounded-xl shadow-sm p-6 mb-6">
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
        <div class="text-right">
            <p class="text-sm text-zinc-500">Progreso</p>
            <p id="salida-progreso" class="text-lg font-bold text-black" data-escaneadas="<?= $progreso['escaneadas'] ?>" data-total="<?= $progreso['total'] ?>">
                <?= $progreso['escaneadas'] ?> de <?= $progreso['total'] ?> escaneados
            </p>
        </div>
    </div>

    <input type="hidden" id="salida-csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" id="salida-orden-id" value="<?= htmlspecialchars($orden->id()) ?>">

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
            <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/cancelar">
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
          onsubmit="var s=this.destino_id; if(!s.value){return false;} return confirm('¿Unir <?= htmlspecialchars($orden->etiquetaDo()) ?> a ' + s.options[s.selectedIndex].text.split(' · ')[0] + '? Este DO quedara cancelado y sus cargas pasan al otro.');">
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
    <h2 class="font-semibold text-black mb-1">Cargas de esta salida</h2>
    <p class="text-xs text-zinc-500 mb-4">Bodega escanea cada bulto al subirlo al contenedor. Las cargas con bultos pendientes aparecen primero.</p>
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
            <tbody id="salida-tbody">
                <?php foreach ($avance as $grupo): $completa = $grupo['escaneadas'] >= $grupo['total']; ?>
                    <tr class="border-b border-slate-200 <?= $completa ? 'bg-emerald-50' : 'bg-slate-50' ?>" data-grupo-carga="<?= htmlspecialchars($grupo['cargaId']) ?>">
                        <td class="py-2 pr-4 font-mono font-semibold text-black">
                            <?php if (!str_starts_with($grupo['cargaId'], 'caja-')): ?>
                                <a href="/cargas/<?= htmlspecialchars($grupo['cargaId']) ?>" class="hover:underline"><?= htmlspecialchars($grupo['tracking']) ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($grupo['tracking']) ?>
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
                        <tr class="border-b border-slate-100" data-codigo="<?= htmlspecialchars($linea->codigoLpn()) ?>" data-carga="<?= htmlspecialchars($grupo['cargaId']) ?>" data-escaneado="<?= $linea->escaneado() ? '1' : '0' ?>">
                            <td class="py-1.5 pr-4 pl-6 font-mono text-zinc-700"><?= htmlspecialchars($linea->codigoLpn()) ?></td>
                            <td class="py-1.5 pr-4" colspan="2"></td>
                            <td class="py-1.5 text-right salida-linea-estado">
                                <?= $linea->escaneado() ? badge('Escaneado', 'emerald') : badge('Pendiente', 'slate') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

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
