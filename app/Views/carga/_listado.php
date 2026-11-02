<?php
/**
 * Listado de cargas con buscador, filtros, totales y paginacion (por AJAX con /cargas/buscar).
 * Lo usan Ingreso de Carga (solo "En bodega") e Historial de cargas ("En salida" y "Todas").
 *
 * @var array<string, string> $vistasListado pestañas valor => texto; la primera es la inicial
 */
$vistaInicial = array_key_first($vistasListado);
?>
<div class="bg-white rounded-xl shadow-sm p-6">
    <input type="hidden" id="cargas-vista" value="<?= htmlspecialchars($vistaInicial) ?>">
    <?php if (count($vistasListado) > 1): ?>
        <div class="mb-4 inline-flex rounded-lg border border-zinc-300 p-1 bg-zinc-50 text-sm" role="tablist">
            <?php foreach ($vistasListado as $vistaValor => $vistaTexto): ?>
                <button type="button" data-cargas-vista="<?= htmlspecialchars($vistaValor) ?>" role="tab"
                        class="px-4 py-1.5 rounded-md font-medium transition <?= $vistaValor === $vistaInicial ? 'bg-white shadow text-zinc-900' : 'text-zinc-500 hover:text-zinc-800' ?>">
                    <?= htmlspecialchars($vistaTexto) ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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
            <select id="cargas-aduanero-filtro" class="rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <option value="">Todos los aduaneros</option>
                <?php foreach ($aduaneros as $aduaneroOpcion): ?>
                    <option value="<?= htmlspecialchars($aduaneroOpcion->identificador()) ?>"><?= htmlspecialchars($aduaneroOpcion->nombre()) ?></option>
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
    <div id="cargas-resumen" class="mb-4">
        <?php require __DIR__ . '/_resumen.php'; ?>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                <th class="py-2 pr-4">Tracking</th>
                <th class="py-2 pr-4">Cliente</th>
                <th class="py-2 pr-4">Proveedor</th>
                <th class="py-2 pr-4">Aduanero</th>
                <th class="py-2 pr-4 text-right">Bultos</th>
                <th class="py-2 pr-4 text-right">Peso (kg)</th>
                <th class="py-2 pr-4 text-right">Cubicaje (p3)</th>
                <th class="py-2 pr-4" title="Estado de la carga completa">Estado carga</th>
                <th class="py-2 pr-4" title="Cuantas etiquetas (LPN) de la carga hay en cada estado">Etiquetas (LPN)</th>
                <th class="py-2 pr-4">Fecha</th>
                <th class="py-2 text-right">Acciones</th>
            </tr>
        </thead>
        <tbody id="cargas-tbody">
            <?php require __DIR__ . '/_tabla_filas.php'; ?>
        </tbody>
    </table>
    </div>
    <div id="cargas-paginacion">
        <?php require __DIR__ . '/_paginacion.php'; ?>
    </div>
</div>

<script>
// Excel: al hacer clic se arma la URL con la busqueda y los filtros que esten puestos.
document.getElementById('btn-excel-cargas').addEventListener('click', function () {
    var params = new URLSearchParams();
    [['buscar-cargas', 'q'], ['cargas-vista', 'vista'], ['cargas-estado-filtro', 'estado'], ['cargas-aduanero-filtro', 'aduanero']].forEach(function (f) {
        var el = document.getElementById(f[0]);
        if (el && el.value.trim() !== '') { params.set(f[1], el.value.trim()); }
    });
    this.href = '/cargas/excel' + (params.toString() ? '?' + params.toString() : '');
});

// Pestañas: cambian el filtro oculto y el listado se recarga.
document.querySelectorAll('[data-cargas-vista]').forEach(function (tab) {
    tab.addEventListener('click', function () {
        var vista = document.getElementById('cargas-vista');
        if (vista.value === tab.getAttribute('data-cargas-vista')) { return; }

        document.querySelectorAll('[data-cargas-vista]').forEach(function (t) {
            var activa = t === tab;
            t.classList.toggle('bg-white', activa);
            t.classList.toggle('shadow', activa);
            t.classList.toggle('text-zinc-900', activa);
            t.classList.toggle('text-zinc-500', !activa);
            t.classList.toggle('hover:text-zinc-800', !activa);
        });

        vista.value = tab.getAttribute('data-cargas-vista');
        vista.dispatchEvent(new Event('change'));
    });
});
</script>
