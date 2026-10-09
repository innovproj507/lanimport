<?php
use App\Libraries\Csrf;
/** @var \Courier\Carga\Domain\Carga $carga */
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var array<int, \Courier\Aduanero\Domain\Aduanero> $aduaneros */
/** @var array<int, \Courier\Proveedor\Domain\Proveedor> $proveedores */
/** @var array<int, \Courier\Pais\Domain\Pais> $paises */
/** @var bool $lpnsGenerados */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Editar Carga';
$pageSubtitle = 'Tracking: ' . (string) $carga->trackingNumero();
$backHref = '/cargas/' . $carga->id();
require __DIR__ . '/../partials/header.php';

$clienteActual = null;
foreach ($clientes as $c) {
    if ($c->id() === $carga->clienteId()) {
        $clienteActual = $c;
        break;
    }
}

$clienteEtiquetaActual = '';
if ($clienteActual !== null) {
    $partes = [];
    if ($clienteActual->codigo()) {
        $partes[] = $clienteActual->codigo();
    }
    $partes[] = $clienteActual->nombre();
    if ($clienteActual->empresa()) {
        $partes[] = $clienteActual->empresa();
    }
    if ((string) $clienteActual->email() !== '') {
        $partes[] = (string) $clienteActual->email();
    }
    $clienteEtiquetaActual = implode(' — ', $partes);
}

$proveedorEtiquetaActual = $carga->proveedorIdentificador() !== null
    ? $carga->proveedorIdentificador() . ' — ' . $carga->proveedor()
    : $carga->proveedor();

$aduaneroEtiquetaActual = $carga->aduanero() !== null
    ? $carga->aduanero()->identificador() . ' — ' . $carga->aduanero()->nombre()
    : '';

$lineas = $carga->lineas();
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-5xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($lpnsGenerados): ?>
    <div class="bg-amber-500/10 text-amber-600 text-sm rounded-lg px-4 py-3 mb-4 border border-amber-500/30 max-w-5xl">
        Esta carga ya tiene LPNs generados. Si cambia las cantidades de las lineas, considere borrar y regenerar los LPNs desde el detalle de la carga.
    </div>
<?php endif; ?>

<form method="post" action="/cargas/<?= htmlspecialchars($carga->id()) ?>/editar" id="form-ingreso-carga" enctype="multipart/form-data" class="max-w-5xl space-y-5">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="bg-white rounded-xl shadow-sm p-6">
        <?= card_header('users', 'Datos del cliente y proveedor', 'amber') ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="relative" id="cliente-picker">
                <label class="block text-sm font-medium text-zinc-700 mb-1">Cliente</label>
                <input type="text" id="cliente-buscar" placeholder="Escriba para buscar cliente..." autocomplete="off"
                       value="<?= htmlspecialchars($clienteEtiquetaActual) ?>"
                       class="w-full rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <input type="hidden" name="cliente_id" id="cliente-id-hidden" value="<?= htmlspecialchars($carga->clienteId()) ?>">
                <p id="cliente-error" class="hidden text-xs text-red-600 mt-1">Selecciona un cliente de la lista.</p>
                <div id="cliente-resultados" class="hidden absolute z-20 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-zinc-300 rounded-lg shadow-lg"></div>
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
            <div class="relative" id="proveedor-picker">
                <label class="block text-sm font-medium text-zinc-700 mb-1">Proveedor</label>
                <input type="text" id="proveedor-buscar" placeholder="Escriba para buscar proveedor..." autocomplete="off"
                       value="<?= htmlspecialchars($proveedorEtiquetaActual) ?>"
                       class="w-full rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <input type="hidden" name="proveedor_identificador" id="proveedor-id-hidden" value="<?= htmlspecialchars((string) $carga->proveedorIdentificador()) ?>">
                <p id="proveedor-error" class="hidden text-xs text-red-600 mt-1">Selecciona un proveedor de la lista.</p>
                <div id="proveedor-resultados" class="hidden absolute z-20 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-zinc-300 rounded-lg shadow-lg"></div>
                <script type="application/json" id="proveedores-data"><?= json_encode(array_map(
                    fn ($proveedor) => [
                        'identificador' => $proveedor->identificador(),
                        'nombre' => $proveedor->nombre(),
                    ],
                    $proveedores,
                ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Fecha de ingreso</label>
                <input type="datetime-local" name="fecha_ingreso" required
                       value="<?= htmlspecialchars($carga->fechaIngreso()->format('Y-m-d\TH:i')) ?>"
                       class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <?= card_header('box', 'Descripcion de la Carga', 'indigo') ?>
            <button type="button" id="btn-agregar-linea-carga" class="inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-100 transition">
                <?= icon_small('plus') ?><span>Agregar Linea</span>
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 pr-3">Descripcion</th>
                        <th class="py-2 pr-3 w-32">Marca</th>
                        <th class="py-2 pr-3 w-24">UoM</th>
                        <th class="py-2 pr-3 w-24">Cantidad</th>
                        <th class="py-2 pr-3 w-24">W (cm)</th>
                        <th class="py-2 pr-3 w-24">H (cm)</th>
                        <th class="py-2 pr-3 w-24">L (cm)</th>
                        <th class="py-2 pr-3 w-24">p3</th>
                        <th class="py-2 pr-3 w-28">Peso (kg)</th>
                        <th class="py-2 pr-3 w-28">Valor</th>
                        <th class="py-2 w-12"></th>
                    </tr>
                </thead>
                <tbody id="carga-lineas-tbody">
                    <?php if (empty($lineas)): ?>
                        <tr class="carga-linea-row border-b border-slate-200">
                            <td class="py-2 pr-3">
                                <input type="text" name="linea_descripcion[]" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="text" name="linea_marca[]" placeholder="Opcional" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="text" name="linea_unidad_medida[]" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_cantidad[]" value="1" class="carga-linea-cantidad w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_ancho[]" class="carga-linea-ancho w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_alto[]" class="carga-linea-alto w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_largo[]" class="carga-linea-largo w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.000001" min="0" name="linea_cubicaje[]" value="0.000000" title="Se calcula automaticamente segun W/H/L, pero puede editarlo manualmente."
                                       class="carga-linea-cubicaje w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_peso[]" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_valor[]" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2">
                                <button type="button" class="carga-linea-quitar inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/10 text-red-600 hover:bg-red-500/20 transition">
                                    <?= icon_small('x') ?>
                                </button>
                            </td>
                        </tr>
                    <?php else: foreach ($lineas as $i => $linea): ?>
                        <tr class="carga-linea-row border-b border-slate-200">
                            <td class="py-2 pr-3">
                                <input type="text" name="linea_descripcion[]" value="<?= htmlspecialchars($linea->descripcion()) ?>" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="text" name="linea_marca[]" value="<?= htmlspecialchars((string) $linea->marca()) ?>" placeholder="Opcional" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="text" name="linea_unidad_medida[]" value="<?= htmlspecialchars($linea->unidadMedida()) ?>" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_cantidad[]" value="<?= htmlspecialchars((string) $linea->cantidad()) ?>" class="carga-linea-cantidad w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_ancho[]" value="<?= htmlspecialchars((string) $linea->ancho()) ?>" class="carga-linea-ancho w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_alto[]" value="<?= htmlspecialchars((string) $linea->alto()) ?>" class="carga-linea-alto w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_largo[]" value="<?= htmlspecialchars((string) $linea->largo()) ?>" class="carga-linea-largo w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.000001" min="0" name="linea_cubicaje[]" value="<?= htmlspecialchars(number_format($linea->cubicajePies(), 6, '.', '')) ?>" title="Se calcula automaticamente segun W/H/L, pero puede editarlo manualmente."
                                       class="carga-linea-cubicaje w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_peso[]" value="<?= htmlspecialchars((string) $linea->peso()) ?>" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2 pr-3">
                                <input type="number" step="0.01" min="0" name="linea_valor[]" value="<?= htmlspecialchars((string) $linea->valor()) ?>" class="w-full rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm">
                            </td>
                            <td class="py-2">
                                <button type="button" class="carga-linea-quitar inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/10 text-red-600 hover:bg-red-500/20 transition<?= ($i === 0 && $lpnsGenerados) ? ' hidden' : '' ?>">
                                    <?= icon_small('x') ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <?= card_header('shield', 'Aduana y contenedor', 'amber') ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="relative" id="aduanero-picker">
                <input type="text" id="aduanero-buscar" placeholder="Escriba para buscar aduanero..." autocomplete="off"
                       value="<?= htmlspecialchars($aduaneroEtiquetaActual) ?>"
                       class="w-full rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <input type="hidden" name="aduanero_identificador" id="aduanero-id-hidden" value="<?= htmlspecialchars($carga->aduanero()?->identificador() ?? '') ?>">
                <div id="aduanero-resultados" class="hidden absolute z-20 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-zinc-300 rounded-lg shadow-lg"></div>
                <script type="application/json" id="aduaneros-data"><?= json_encode(array_map(
                    fn ($aduanero) => [
                        'identificador' => $aduanero->identificador(),
                        'nombre' => $aduanero->nombre(),
                    ],
                    $aduaneros,
                ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
            </div>
            <input type="text" name="numero_contenedor" placeholder="Numero de contenedor (opcional)"
                   value="<?= htmlspecialchars($carga->numeroContenedor() !== null ? (string) $carga->numeroContenedor() : '') ?>"
                   class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2">
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <?= card_header('receipt', 'Notas y Observaciones', 'slate') ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <textarea name="notas" rows="4" placeholder="Notas u observaciones adicionales (opcional)"
                          class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"><?= htmlspecialchars((string) $carga->notas()) ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Factura del proveedor (opcional)</label>
                <input type="file" name="factura_proveedor" accept=".pdf,.jpg,.jpeg,.png"
                       class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-200 file:text-zinc-800 file:text-sm hover:file:bg-slate-300 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <p class="text-xs text-zinc-500 mt-1">
                    PDF, JPG o PNG. Maximo 5 MB. Deje vacio para conservar la actual.
                    <?php if ($carga->facturaProveedorRuta()): ?>
                        <a href="/<?= htmlspecialchars($carga->facturaProveedorRuta()) ?>" target="_blank" class="text-amber-600 hover:underline">Ver factura actual</a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
            <?= icon_small('check') ?><span>Guardar cambios</span>
        </button>
        <a href="/cargas/<?= htmlspecialchars($carga->id()) ?>" class="inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-6 py-2.5 font-medium hover:bg-slate-300 transition">
            <span>Cancelar</span>
        </a>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
