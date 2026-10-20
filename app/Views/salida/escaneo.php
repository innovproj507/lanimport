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
$headerActions = header_button('/salidas/' . $orden->id() . '/reporte', 'chart', 'Reporte de salida', 'amber')
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
    <fieldset <?= $cancelada ? 'disabled' : '' ?> class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
            <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/cancelar">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button type="submit" class="inline-flex items-center gap-2 bg-red-500/10 text-red-600 rounded-lg px-4 py-2 text-sm font-medium hover:bg-red-500/20 transition">
                    <?= icon_small('x') ?><span>Cancelar Orden</span>
                </button>
            </form>
        </div>
    <?php else: ?>
        <p class="mt-4 text-sm font-medium <?= $confirmada ? 'text-emerald-600' : 'text-red-600' ?>">
            Esta orden esta <?= htmlspecialchars($orden->estado()->label()) ?>.
        </p>
    <?php endif; ?>
</div>

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
$fotosTitulo = 'Fotos de la salida';
$fotosAyuda = 'Evidencia del despacho: contenedor, sello y carga estibada. Bodega las toma desde la app movil; tambien se pueden agregar aqui.';
$fotosUrlSubir = '/bodega/api/salidas/' . $orden->id() . '/fotos';
$fotosVolver = '/salidas/' . $orden->id();
require __DIR__ . '/../evidencia/_fotos.php';
?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
