<?php
use App\Libraries\Csrf;
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var array<int, \Courier\Aduanero\Domain\Aduanero> $aduaneros */
/** @var array<int, \Courier\Proveedor\Domain\Proveedor> $proveedores */
/** @var array<int, \Courier\Pais\Domain\Pais> $paises */
/** @var array<int, \Courier\Carga\Domain\Carga> $cargas */
/** @var array<string, string> $nombresClientes */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalPages */
/** @var bool $mostrarFormulario */
/** @var array<int, array{actaId: string, codigo: string, clienteId: string, clienteNombre: string, marca: ?string, totalRecibido: int, fechaRecepcion: string}> $actasSinCarga */
/** @var string|null $actaPreseleccionada */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Ingreso de Carga';
$pageSubtitle = 'Cargas ingresadas al sistema';
$headerActions = '<button type="button" id="btn-nuevo-ingreso" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">'
    . icon_small('plus') . '<span>Nuevo Ingreso</span></button>';
require __DIR__ . '/../partials/header.php';
?>

<div id="seccion-historial-cargas" class="<?= $mostrarFormulario ? 'hidden' : '' ?>">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="mb-4 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-2">
                <?= icon_small('search', 'w-4 h-4 text-zinc-500') ?>
                <input type="text" id="buscar-cargas" placeholder="Buscar por tracking, cliente, proveedor o contenedor..."
                       class="w-full max-w-sm rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <select id="cargas-estado-filtro" class="rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    <option value="">Todos los estados</option>
                    <?php foreach (\Courier\Carga\Domain\CargaStatus::cases() as $estadoOpcion): ?>
                        <option value="<?= htmlspecialchars($estadoOpcion->value) ?>"><?= htmlspecialchars($estadoOpcion->label()) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="flex items-center gap-2 text-sm text-zinc-600">
                    <label for="cargas-per-page">Mostrar</label>
                    <select id="cargas-per-page" class="rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
                        <?php foreach ([5, 10, 25] as $opcion): ?>
                            <option value="<?= $opcion ?>" <?= $perPage === $opcion ? 'selected' : '' ?>><?= $opcion ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>filas</span>
                </div>
            </div>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">Tracking</th>
                    <th class="py-2 pr-4">Cliente</th>
                    <th class="py-2 pr-4">Proveedor</th>
                    <th class="py-2 pr-4">Estado</th>
                    <th class="py-2 pr-4">Fecha</th>
                    <th class="py-2 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="cargas-tbody">
                <?php require __DIR__ . '/_tabla_filas.php'; ?>
            </tbody>
        </table>
        <div id="cargas-paginacion">
            <?php require __DIR__ . '/_paginacion.php'; ?>
        </div>
    </div>
</div>

<div id="seccion-form-ingreso" class="<?= $mostrarFormulario ? '' : 'hidden' ?>">
    <div class="mb-4">
        <button type="button" id="btn-cancelar-ingreso" class="inline-flex items-center gap-2 text-zinc-600 hover:text-black text-sm font-medium">
            <?= icon_small('arrow-left', 'w-4 h-4') ?><span>Volver al historial</span>
        </button>
    </div>

    <?php if (!empty($error)): ?>
        <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-5xl">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($clientes)): ?>
        <div class="bg-amber-500/10 text-amber-600 text-sm rounded-lg px-4 py-3 mb-4 border border-amber-500/30 max-w-5xl">
            No hay clientes registrados todavia. <a href="/clientes/nuevo" class="underline font-medium">Registre un cliente</a> antes de continuar.
        </div>
    <?php endif; ?>

    <?php if (empty($aduaneros)): ?>
        <div class="bg-amber-500/10 text-amber-600 text-sm rounded-lg px-4 py-3 mb-4 border border-amber-500/30 max-w-5xl">
            No hay aduaneros registrados todavia. <a href="/aduaneros/nuevo" class="underline font-medium">Registre un aduanero</a> antes de continuar.
        </div>
    <?php endif; ?>

    <?php if (empty($proveedores)): ?>
        <div class="bg-amber-500/10 text-amber-600 text-sm rounded-lg px-4 py-3 mb-4 border border-amber-500/30 max-w-5xl">
            No hay proveedores registrados todavia. <a href="/proveedores/nuevo" class="underline font-medium">Registre un proveedor</a> antes de continuar.
        </div>
    <?php endif; ?>

    <form method="post" action="/cargas" id="form-ingreso-carga" enctype="multipart/form-data" class="max-w-5xl space-y-5">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

        <?php if ($actasSinCarga !== []): ?>
            <?php
            $clientesPorId = [];
            foreach ($clientes as $c) {
                $clientesPorId[$c->id()] = implode(' — ', array_filter([$c->codigo(), $c->nombre(), $c->empresa(), (string) $c->email()]));
            }
            ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                <?= card_header('receipt', 'Mercancia ya recibida por bodega', 'amber') ?>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Acta de recepcion (opcional)</label>
                <select name="acta_recepcion_id" id="acta-recepcion-select"
                        class="w-full md:w-2/3 rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    <option value="">— Ninguna: la carga no tiene acta todavia —</option>
                    <?php foreach ($actasSinCarga as $a): ?>
                        <option value="<?= htmlspecialchars($a['actaId']) ?>"
                                data-cliente-id="<?= htmlspecialchars($a['clienteId']) ?>"
                                data-cliente-etiqueta="<?= htmlspecialchars($clientesPorId[$a['clienteId']] ?? $a['clienteNombre']) ?>"
                                <?= $a['actaId'] === $actaPreseleccionada ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['codigo'] . ' — ' . $a['clienteNombre'] . ($a['marca'] ? ' — ' . $a['marca'] : '') . ' — ' . $a['totalRecibido'] . ' bulto(s) — ' . (new DateTimeImmutable($a['fechaRecepcion']))->format('d/m/Y')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-zinc-500 mt-1">Al elegir un acta se selecciona su cliente, y al guardar la carga el acta queda vinculada a ella.</p>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var select = document.getElementById('acta-recepcion-select');

                function aplicarCliente() {
                    var opcion = select.options[select.selectedIndex];

                    if (!opcion || !opcion.getAttribute('data-cliente-id')) {
                        return;
                    }

                    document.getElementById('cliente-id-hidden').value = opcion.getAttribute('data-cliente-id');
                    document.getElementById('cliente-buscar').value = opcion.getAttribute('data-cliente-etiqueta');
                    document.getElementById('cliente-error').classList.add('hidden');
                }

                select.addEventListener('change', aplicarCliente);
                aplicarCliente();
            });
            </script>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <?= card_header('users', 'Datos del cliente y proveedor', 'amber') ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="relative" id="cliente-picker">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-zinc-700">Cliente</label>
                        <button type="button" id="btn-cliente-nuevo" title="Agregar cliente"
                                class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-black hover:bg-amber-400 transition">
                            <?= icon_small('plus', 'w-3.5 h-3.5') ?>
                        </button>
                    </div>
                    <input type="text" id="cliente-buscar" placeholder="Escriba para buscar cliente..." autocomplete="off"
                           class="w-full rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    <input type="hidden" name="cliente_id" id="cliente-id-hidden">
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
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-zinc-700">Proveedor</label>
                        <button type="button" id="btn-proveedor-nuevo" title="Agregar proveedor"
                                class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500 text-black hover:bg-amber-400 transition">
                            <?= icon_small('plus', 'w-3.5 h-3.5') ?>
                        </button>
                    </div>
                    <input type="text" id="proveedor-buscar" placeholder="Escriba para buscar proveedor..." autocomplete="off"
                           class="w-full rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    <input type="hidden" name="proveedor_identificador" id="proveedor-id-hidden">
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
                           value="<?= htmlspecialchars((new DateTimeImmutable())->format('Y-m-d\TH:i')) ?>"
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
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <?= card_header('shield', 'Aduana y contenedor', 'amber') ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="relative" id="aduanero-picker">
                    <input type="text" id="aduanero-buscar" placeholder="Escriba para buscar aduanero..." autocomplete="off"
                           class="w-full rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    <input type="hidden" name="aduanero_identificador" id="aduanero-id-hidden">
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
                       class="rounded-lg border border-zinc-300 bg-white text-black px-3 py-2">
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <?= card_header('receipt', 'Notas y Observaciones', 'slate') ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <textarea name="notas" rows="4" placeholder="Notas u observaciones adicionales (opcional)"
                              class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Factura del proveedor (opcional)</label>
                    <input type="file" name="factura_proveedor" accept=".pdf,.jpg,.jpeg,.png"
                           class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-200 file:text-zinc-800 file:text-sm hover:file:bg-slate-300 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    <p class="text-xs text-zinc-500 mt-1">PDF, JPG o PNG. Maximo 5 MB.</p>
                </div>
            </div>
        </div>

        <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
            <?= icon_small('check') ?><span>Registrar carga</span>
        </button>
    </form>

    <div id="modal-cliente-nuevo" class="hidden fixed inset-0 bg-black/70 z-50 items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full p-6 relative">
            <button type="button" class="modal-cerrar-btn absolute top-3 right-3 w-8 h-8 rounded-lg flex items-center justify-center text-zinc-500 hover:bg-slate-100 hover:text-black transition"
                    data-modal="modal-cliente-nuevo" title="Cerrar">
                <?= icon_small('x') ?>
            </button>
            <?= card_header('users', 'Nuevo cliente', 'amber') ?>
            <p id="cliente-rapido-error" class="hidden bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-3 border border-red-500/30"></p>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre</label>
                    <input type="text" id="cliente-rapido-nombre" required
                           class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Correo electronico</label>
                    <input type="email" id="cliente-rapido-email" required
                           class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Empresa (opcional)</label>
                    <input type="text" id="cliente-rapido-empresa"
                           class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Pais</label>
                    <select id="cliente-rapido-pais" required
                            class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                        <option value="">Seleccione pais...</option>
                        <?php foreach ($paises as $pais): ?>
                            <option value="<?= htmlspecialchars($pais->nombre()) ?>"><?= htmlspecialchars($pais->nombre()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" class="modal-cerrar-btn inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-300 transition"
                        data-modal="modal-cliente-nuevo">
                    Cancelar
                </button>
                <button type="button" id="btn-cliente-rapido-guardar"
                        class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
                    <?= icon_small('check') ?><span>Guardar cliente</span>
                </button>
            </div>
        </div>
    </div>

    <div id="modal-proveedor-nuevo" class="hidden fixed inset-0 bg-black/70 z-50 items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full p-6 relative">
            <button type="button" class="modal-cerrar-btn absolute top-3 right-3 w-8 h-8 rounded-lg flex items-center justify-center text-zinc-500 hover:bg-slate-100 hover:text-black transition"
                    data-modal="modal-proveedor-nuevo" title="Cerrar">
                <?= icon_small('x') ?>
            </button>
            <?= card_header('truck', 'Nuevo proveedor', 'sky') ?>
            <p id="proveedor-rapido-error" class="hidden bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-3 border border-red-500/30"></p>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Identificador</label>
                    <input type="text" id="proveedor-rapido-identificador" required placeholder="Ej: PROV-0001"
                           class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre</label>
                    <input type="text" id="proveedor-rapido-nombre" required
                           class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" class="modal-cerrar-btn inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-300 transition"
                        data-modal="modal-proveedor-nuevo">
                    Cancelar
                </button>
                <button type="button" id="btn-proveedor-rapido-guardar"
                        class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
                    <?= icon_small('check') ?><span>Guardar proveedor</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrfInput = document.querySelector('#form-ingreso-carga input[name="_csrf"]');
    var csrf = csrfInput ? csrfInput.value : '';

    function abrirModal(id) {
        var modal = document.getElementById(id);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModal(id) {
        var modal = document.getElementById(id);
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    var btnClienteNuevo = document.getElementById('btn-cliente-nuevo');
    var btnProveedorNuevo = document.getElementById('btn-proveedor-nuevo');

    if (btnClienteNuevo) {
        btnClienteNuevo.addEventListener('click', function () {
            abrirModal('modal-cliente-nuevo');
        });
    }

    if (btnProveedorNuevo) {
        btnProveedorNuevo.addEventListener('click', function () {
            abrirModal('modal-proveedor-nuevo');
        });
    }

    document.querySelectorAll('.modal-cerrar-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            cerrarModal(btn.getAttribute('data-modal'));
        });
    });

    document.querySelectorAll('[id^="modal-"]').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                cerrarModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('[id^="modal-"]').forEach(function (modal) {
            if (!modal.classList.contains('hidden')) {
                cerrarModal(modal.id);
            }
        });
    });

    // Cliente rapido
    var btnClienteGuardar = document.getElementById('btn-cliente-rapido-guardar');
    var clienteError = document.getElementById('cliente-rapido-error');

    if (btnClienteGuardar) {
        btnClienteGuardar.addEventListener('click', function () {
            var nombre = document.getElementById('cliente-rapido-nombre').value.trim();
            var email = document.getElementById('cliente-rapido-email').value.trim();
            var empresa = document.getElementById('cliente-rapido-empresa').value.trim();
            var pais = document.getElementById('cliente-rapido-pais').value;

            clienteError.classList.add('hidden');

            if (nombre === '' || email === '' || pais === '') {
                clienteError.textContent = 'Nombre, correo y pais son obligatorios.';
                clienteError.classList.remove('hidden');
                return;
            }

            var body = new URLSearchParams();
            body.set('_csrf', csrf);
            body.set('nombre', nombre);
            body.set('email', email);
            body.set('empresa', empresa);
            body.set('pais', pais);

            btnClienteGuardar.disabled = true;

            fetch('/clientes/rapido', { method: 'POST', body: body })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    btnClienteGuardar.disabled = false;

                    if (!data.success) {
                        clienteError.textContent = data.message || 'No se pudo guardar el cliente.';
                        clienteError.classList.remove('hidden');
                        return;
                    }

                    if (window.ClientePicker) {
                        window.ClientePicker.agregarYSeleccionar(data.cliente);
                    }

                    document.getElementById('cliente-rapido-nombre').value = '';
                    document.getElementById('cliente-rapido-email').value = '';
                    document.getElementById('cliente-rapido-empresa').value = '';
                    document.getElementById('cliente-rapido-pais').value = '';

                    cerrarModal('modal-cliente-nuevo');
                })
                .catch(function () {
                    btnClienteGuardar.disabled = false;
                    clienteError.textContent = 'Error de conexion, intenta de nuevo.';
                    clienteError.classList.remove('hidden');
                });
        });
    }

    // Proveedor rapido
    var btnProveedorGuardar = document.getElementById('btn-proveedor-rapido-guardar');
    var proveedorError = document.getElementById('proveedor-rapido-error');

    if (btnProveedorGuardar) {
        btnProveedorGuardar.addEventListener('click', function () {
            var identificador = document.getElementById('proveedor-rapido-identificador').value.trim();
            var nombre = document.getElementById('proveedor-rapido-nombre').value.trim();

            proveedorError.classList.add('hidden');

            if (identificador === '' || nombre === '') {
                proveedorError.textContent = 'Identificador y nombre son obligatorios.';
                proveedorError.classList.remove('hidden');
                return;
            }

            var body = new URLSearchParams();
            body.set('_csrf', csrf);
            body.set('identificador', identificador);
            body.set('nombre', nombre);

            btnProveedorGuardar.disabled = true;

            fetch('/proveedores/rapido', { method: 'POST', body: body })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    btnProveedorGuardar.disabled = false;

                    if (!data.success) {
                        proveedorError.textContent = data.message || 'No se pudo guardar el proveedor.';
                        proveedorError.classList.remove('hidden');
                        return;
                    }

                    if (window.ProveedorPicker) {
                        window.ProveedorPicker.agregarYSeleccionar(data.proveedor);
                    }

                    document.getElementById('proveedor-rapido-identificador').value = '';
                    document.getElementById('proveedor-rapido-nombre').value = '';

                    cerrarModal('modal-proveedor-nuevo');
                })
                .catch(function () {
                    btnProveedorGuardar.disabled = false;
                    proveedorError.textContent = 'Error de conexion, intenta de nuevo.';
                    proveedorError.classList.remove('hidden');
                });
        });
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
