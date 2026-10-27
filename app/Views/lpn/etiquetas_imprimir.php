<?php

declare(strict_types=1);

/** @var \Courier\Carga\Domain\Carga $carga */
/** @var array<int, \Courier\Lpn\Domain\Lpn> $lpns */
/** @var int $imagenesFaltan etiquetas cuya imagen aun no se dibuja: no se deja imprimir hasta que esten todas */
$imagenesFaltan ??= 0;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir etiquetas — <?= htmlspecialchars((string) $carga->trackingNumero()) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #fff; }
        .label {
            width: 10cm; height: 7.5cm; background: #fff;
            display: flex; align-items: center; justify-content: center;
            page-break-after: always; break-after: page;
        }
        .label:last-child { page-break-after: avoid; break-after: avoid; }
        .label img { width: 100%; height: 100%; object-fit: contain; }
        .print-bar {
            background: #1a1d27; color: #f1f5f9; padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            font-family: 'Segoe UI', Arial, sans-serif; flex-wrap: wrap;
        }
        .print-bar h2 { font-size: 14px; font-weight: 700; }
        .print-bar p { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .btn-back {
            color: #94a3b8; font-size: 13px; text-decoration: none;
            padding: 8px 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,.15);
        }
        .btn-print {
            background: #059669; color: #fff; border: none; padding: 10px 22px;
            border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .btn-print:disabled { background: #475569; cursor: wait; }
        .aviso { padding: 24px; font-family: Arial, sans-serif; color: #92400e; font-size: 15px; }
        .aviso-impresion { display: none; }
        @media print {
            @page { size: 10cm 7.5cm; margin: 0; }
            .print-bar { display: none !important; }
            /* Si se imprime (Ctrl+P) antes de que carguen todas, no salen etiquetas en blanco. */
            body.cargando .label { display: none !important; }
            body.cargando .aviso-impresion { display: block; padding: 1cm; font-size: 14pt; }
        }
    </style>
</head>
<body class="cargando">
<div class="print-bar">
    <div>
        <h2>Imprimir etiquetas de LPN</h2>
        <p><?= count($lpns) ?> etiquetas &middot; 1 por pagina (10cm &times; 7.5cm) &middot; Tracking: <?= htmlspecialchars((string) $carga->trackingNumero()) ?></p>
        <p id="estado-carga"></p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="javascript:history.back()" class="btn-back">&larr; Volver</a>
        <button type="button" class="btn-print" id="btn-imprimir" disabled onclick="window.print()">Cargando...</button>
    </div>
</div>
<p class="aviso-impresion">Las etiquetas aun no terminan de cargar. Espere a que el boton diga "Imprimir" e intente de nuevo.</p>
<?php if (empty($lpns)): ?>
    <p style="padding:24px;font-family:Arial,sans-serif;color:#64748b;">No hay LPNs generados para esta carga.</p>
<?php elseif ($imagenesFaltan > 0): ?>
    <p class="aviso">
        Todavia se estan preparando <?= $imagenesFaltan ?> de <?= count($lpns) ?> etiquetas.
        Vuelva a la pantalla de recepcion de la carga, espere a que la barra termine y luego imprima.
    </p>
<?php else: foreach ($lpns as $lpn): ?>
    <div class="label">
        <img src="/uploads/lpns/<?= htmlspecialchars((string) $lpn->codigo()) ?>.png?v=<?= @filemtime(FCPATH . 'uploads/lpns/' . $lpn->codigo() . '.png') ?>" alt="Etiqueta <?= htmlspecialchars((string) $lpn->codigo()) ?>">
    </div>
<?php endforeach; endif; ?>
<script>
// Solo se deja imprimir cuando todas las imagenes cargaron: con cientos de etiquetas, imprimir
// antes sacaba algunas en blanco (solo el texto "Etiqueta LPN-..."). Las que fallan se reintentan.
(function () {
    var imgs = Array.prototype.slice.call(document.querySelectorAll('.label img'));
    var boton = document.getElementById('btn-imprimir');
    var estado = document.getElementById('estado-carga');
    var reintentos = {};

    if (imgs.length === 0) {
        boton.textContent = <?= $imagenesFaltan > 0 ? "'Preparando etiquetas...'" : "'Sin etiquetas'" ?>;
        return;
    }

    function base(img) {
        return img.src.split('&r=')[0];
    }

    function revisar() {
        var listas = imgs.filter(function (img) { return img.complete && img.naturalWidth > 0; }).length;
        var fallidas = imgs.filter(function (img) { return (reintentos[base(img)] || 0) >= 3 && img.naturalWidth === 0; }).length;

        if (listas === imgs.length) {
            document.body.classList.remove('cargando');
            boton.disabled = false;
            boton.textContent = 'Imprimir';
            estado.textContent = 'Todas las etiquetas cargaron.';
            return;
        }

        boton.textContent = 'Cargando ' + listas + ' de ' + imgs.length + '...';
        estado.textContent = fallidas > 0 ? fallidas + ' etiquetas no cargaron. Recargue la pagina.' : '';
    }

    imgs.forEach(function (img) {
        img.addEventListener('load', revisar);
        img.addEventListener('error', function () {
            var url = base(img);
            reintentos[url] = (reintentos[url] || 0) + 1;
            if (reintentos[url] < 3) {
                setTimeout(function () { img.src = url + '&r=' + Date.now(); }, 1500);
            }
            revisar();
        });
    });
    revisar();
})();
</script>
</body>
</html>
