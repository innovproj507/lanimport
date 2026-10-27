<?php
/**
 * Visor de documentos (facturas en PDF o imagen) en un modal, sin salir de la pagina.
 * Uso: <a href="/uploads/..." data-doc-ver data-doc-titulo="Factura · LAN-2026-XXXXXX">.
 * Ctrl/clic central abre en otra pestaña; si el navegador no muestra PDF (celulares), tambien.
 */
?>
<div id="doc-visor" class="hidden fixed inset-0 z-[60] bg-black/80 items-center justify-center p-2 sm:p-6" role="dialog" aria-modal="true">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl h-full max-h-[94vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-200">
            <p id="doc-visor-titulo" class="font-semibold text-black truncate"></p>
            <div class="flex items-center gap-2 shrink-0">
                <a id="doc-visor-abrir" href="#" target="_blank" rel="noopener" class="text-sm text-amber-700 hover:underline">Abrir en otra pestaña</a>
                <a id="doc-visor-descargar" href="#" download class="text-sm text-amber-700 hover:underline">Descargar</a>
                <button type="button" data-doc-cerrar title="Cerrar (Esc)" class="w-9 h-9 rounded-lg flex items-center justify-center text-2xl text-zinc-600 hover:bg-slate-100">&times;</button>
            </div>
        </div>
        <div id="doc-visor-cuerpo" class="flex-1 bg-slate-100 flex items-center justify-center overflow-auto"></div>
    </div>
</div>
<script>
(function () {
    var visor = document.getElementById('doc-visor');
    var cuerpo = document.getElementById('doc-visor-cuerpo');

    function cerrar() {
        visor.classList.add('hidden');
        visor.classList.remove('flex');
        cuerpo.innerHTML = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('[data-doc-ver]');

        if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) {
            return;
        }

        var url = a.getAttribute('href');
        var ruta = url.split('?')[0].toLowerCase();
        var esPdf = /\.pdf$/.test(ruta);
        var esImagen = /\.(jpe?g|png|webp|gif)$/.test(ruta);

        // Sin visor de PDF en el navegador (la mayoria de celulares): se abre aparte, como antes.
        if ((!esPdf && !esImagen) || (esPdf && navigator.pdfViewerEnabled === false)) {
            return;
        }

        e.preventDefault();
        document.getElementById('doc-visor-titulo').textContent = a.getAttribute('data-doc-titulo') || 'Documento';
        document.getElementById('doc-visor-abrir').href = url;
        document.getElementById('doc-visor-descargar').href = url;
        cuerpo.innerHTML = '';

        var el = document.createElement(esPdf ? 'iframe' : 'img');
        el.src = url;
        el.className = esPdf ? 'w-full h-full border-0 bg-white' : 'max-w-full max-h-full object-contain';
        el.title = 'Documento';
        cuerpo.appendChild(el);

        visor.classList.remove('hidden');
        visor.classList.add('flex');
        document.body.style.overflow = 'hidden';
    });

    visor.addEventListener('click', function (e) {
        if (e.target === visor || e.target.closest('[data-doc-cerrar]')) {
            cerrar();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !visor.classList.contains('hidden')) {
            cerrar();
        }
    });
})();
</script>
