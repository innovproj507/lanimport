<?php
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Impresoras';
$pageSubtitle = 'Configura que impresora usa este equipo para etiquetas';
// Solo gerente/admin tienen la pagina de Configuracion a la que volver.
$backHref = in_array($_SESSION['usuario_rol'] ?? null, ['gerente', 'admin'], true) ? '/configuracion' : null;
require __DIR__ . '/../partials/header.php';
?>

<div id="agente-sin-conexion" class="hidden bg-amber-500/10 text-amber-700 text-sm rounded-lg px-4 py-3 mb-4 border border-amber-500/30 max-w-2xl">
    No se detecto el <strong>Agente de Impresion LAN</strong> corriendo en este equipo. Es un servicio propio
    del sistema (sin programas de terceros) que permite imprimir directamente a las impresoras de este equipo
    desde el navegador. Revisa <code>herramientas/agente-impresion/LEEME.md</code> en el repositorio para instalarlo,
    o pide al equipo de sistemas que lo instale en esta estacion, luego recarga esta pagina.
</div>

<div id="agente-error" class="hidden bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-2xl"></div>

<div class="bg-white rounded-xl shadow-sm p-6 max-w-2xl space-y-6">
    <div class="flex items-center gap-2">
        <span id="agente-estado-punto" class="w-2.5 h-2.5 rounded-full bg-zinc-300"></span>
        <span id="agente-estado-texto" class="text-sm text-zinc-600">Conectando con el agente de impresion...</span>
    </div>

    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Impresora de etiquetas (termica)</label>
        <p class="text-xs text-zinc-500 mb-2">Usada para imprimir etiquetas de tracking de carga y codigos LPN.</p>
        <div class="flex items-center gap-2">
            <select id="select-impresora-etiquetas" disabled
                    class="flex-1 rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <option value="">Cargando impresoras...</option>
            </select>
            <button type="button" id="btn-probar-etiquetas" disabled
                    class="inline-flex items-center gap-2 bg-white border border-zinc-300 text-zinc-700 rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-100 transition disabled:opacity-40">
                Probar
            </button>
        </div>
    </div>

    <div class="flex items-center gap-3 pt-2 border-t border-slate-200">
        <button type="button" id="btn-guardar-impresoras" disabled
                class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition disabled:opacity-40">
            <?= icon_small('check') ?><span>Guardar</span>
        </button>
        <span id="agente-guardado-msg" class="hidden text-sm text-emerald-600">Guardado en este equipo.</span>
    </div>

    <p class="text-xs text-zinc-500">
        Esta configuracion se guarda solo en este navegador/equipo. Cada estacion de trabajo debe
        configurar su propia impresora la primera vez que la use. Los documentos (facturas/reportes)
        se siguen imprimiendo con el dialogo normal del navegador, donde puedes elegir cualquier impresora.
    </p>
</div>

<script src="/assets/js/local-print.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var puntoEstado = document.getElementById('agente-estado-punto');
    var textoEstado = document.getElementById('agente-estado-texto');
    var avisoSinConexion = document.getElementById('agente-sin-conexion');
    var avisoError = document.getElementById('agente-error');
    var selectEtiquetas = document.getElementById('select-impresora-etiquetas');
    var btnGuardar = document.getElementById('btn-guardar-impresoras');
    var btnProbarEtiquetas = document.getElementById('btn-probar-etiquetas');
    var msgGuardado = document.getElementById('agente-guardado-msg');

    function mostrarError(mensaje) {
        avisoError.textContent = mensaje;
        avisoError.classList.remove('hidden');
    }

    function llenarSelect(select, impresoras, seleccionActual) {
        select.innerHTML = '<option value="">Sin seleccionar</option>';

        impresoras.forEach(function (nombre) {
            var option = document.createElement('option');
            option.value = nombre;
            option.textContent = nombre;
            option.selected = nombre === seleccionActual;
            select.appendChild(option);
        });

        select.disabled = false;
    }

    LocalPrint.estado().then(function () {
        puntoEstado.classList.remove('bg-zinc-300');
        puntoEstado.classList.add('bg-emerald-500');
        textoEstado.textContent = 'Conectado con el agente de impresion';

        return LocalPrint.listarImpresoras();
    }).then(function (impresoras) {
        llenarSelect(selectEtiquetas, impresoras, LocalPrint.getImpresoraEtiquetas());
        btnGuardar.disabled = false;
        btnProbarEtiquetas.disabled = false;
    }).catch(function (err) {
        puntoEstado.classList.remove('bg-zinc-300');
        puntoEstado.classList.add('bg-red-500');
        textoEstado.textContent = 'No conectado';
        avisoSinConexion.classList.remove('hidden');
        mostrarError(err && err.message ? err.message : String(err));
    });

    btnGuardar.addEventListener('click', function () {
        LocalPrint.setImpresoraEtiquetas(selectEtiquetas.value);
        msgGuardado.classList.remove('hidden');
        setTimeout(function () { msgGuardado.classList.add('hidden'); }, 2500);
    });

    btnProbarEtiquetas.addEventListener('click', function () {
        if (!selectEtiquetas.value) {
            mostrarError('Selecciona una impresora de etiquetas primero.');
            return;
        }

        LocalPrint.imprimirImagen(selectEtiquetas.value, window.location.origin + '/assets/images/fondo.png')
            .catch(function (err) { mostrarError(err && err.message ? err.message : String(err)); });
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
