<?php
use App\Libraries\Csrf;
/** @var \Courier\Lpn\Domain\EtiquetaLpnConfig $etiquetaConfig */
/** @var string $volver URL a la que regresar despues de guardar */
?>
<h2 class="font-semibold text-black mb-1 flex items-center gap-2"><?= icon_small('box', 'w-4 h-4 text-zinc-500') ?> Plantilla de etiqueta LPN</h2>
<p class="text-xs text-zinc-500 mb-4">
    Elige que datos incluir en las etiquetas de LPN/bulto. Aplica a todas las etiquetas que se generen de aqui en adelante (no cambia las ya impresas).
</p>
<form method="post" action="/lpn/plantilla-etiqueta" id="form-plantilla-etiqueta" class="space-y-2 mb-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" name="volver" value="<?= htmlspecialchars($volver) ?>">
    <label class="flex items-center gap-2 text-sm text-zinc-700">
        <input type="checkbox" name="mostrar_marca" class="plantilla-check rounded border-zinc-300 text-amber-500 focus:ring-amber-400" data-preview="prev-marca" <?= $etiquetaConfig->mostrarMarca() ? 'checked' : '' ?>>
        Marca
    </label>
    <label class="flex items-center gap-2 text-sm text-zinc-700">
        <input type="checkbox" name="mostrar_proveedor" class="plantilla-check rounded border-zinc-300 text-amber-500 focus:ring-amber-400" data-preview="prev-proveedor" <?= $etiquetaConfig->mostrarProveedor() ? 'checked' : '' ?>>
        Nombre del proveedor
    </label>
    <label class="flex items-center gap-2 text-sm text-zinc-700">
        <input type="checkbox" name="mostrar_serie" class="plantilla-check rounded border-zinc-300 text-amber-500 focus:ring-amber-400" data-preview="prev-serie" <?= $etiquetaConfig->mostrarSerie() ? 'checked' : '' ?>>
        Serie (bulto X/Y) &mdash; solo paises marcados en Paises
    </label>
    <label class="flex items-center gap-2 text-sm text-zinc-700">
        <input type="checkbox" name="mostrar_descripcion" class="plantilla-check rounded border-zinc-300 text-amber-500 focus:ring-amber-400" data-preview="prev-desc" <?= $etiquetaConfig->mostrarDescripcion() ? 'checked' : '' ?>>
        Descripcion del producto
    </label>
    <button type="submit" class="mt-2 inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400 transition">
        <?= icon_small('check') ?><span>Guardar plantilla</span>
    </button>
</form>

<p class="text-xs font-medium text-zinc-500 uppercase tracking-wide mb-2">Vista previa</p>
<div class="border border-zinc-300 rounded-lg bg-white p-3 text-center">
    <p class="text-[10px] uppercase text-zinc-500">LAN IMPORT - EXPORT S.A.</p>
    <hr class="my-1 border-zinc-300">
    <p id="prev-marca" class="font-black text-base leading-tight">MARCA EJEMPLO</p>
    <p id="prev-proveedor" class="font-black text-sm leading-tight">PROVEEDOR EJEMPLO</p>
    <p id="prev-serie" class="font-black text-xl leading-tight">1/5</p>
    <p id="prev-desc" class="text-[11px] text-zinc-600">Descripcion de ejemplo del producto</p>
    <div class="mt-2 mx-auto w-2/3 h-6 bg-[repeating-linear-gradient(90deg,#000_0,#000_2px,transparent_2px,transparent_4px)]"></div>
    <p class="text-[10px] font-mono mt-1">LPN-C2D116-07</p>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-plantilla-etiqueta');

    if (!form) {
        return;
    }

    form.querySelectorAll('.plantilla-check').forEach(function (chk) {
        var target = document.getElementById(chk.getAttribute('data-preview'));

        function sync() {
            if (target) {
                target.classList.toggle('hidden', !chk.checked);
            }
        }

        sync();
        chk.addEventListener('change', sync);
    });
});
</script>
