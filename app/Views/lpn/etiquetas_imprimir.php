<?php

declare(strict_types=1);

/** @var \Courier\Carga\Domain\Carga $carga */
/** @var array<int, \Courier\Lpn\Domain\Lpn> $lpns */
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
        @media print {
            @page { size: 10cm 7.5cm; margin: 0; }
            .print-bar { display: none !important; }
        }
    </style>
</head>
<body>
<div class="print-bar">
    <div>
        <h2>Imprimir etiquetas de LPN</h2>
        <p><?= count($lpns) ?> etiquetas &middot; 1 por pagina (10cm &times; 7.5cm) &middot; Tracking: <?= htmlspecialchars((string) $carga->trackingNumero()) ?></p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="javascript:history.back()" class="btn-back">&larr; Volver</a>
        <button type="button" class="btn-print" onclick="window.print()">Imprimir</button>
    </div>
</div>
<?php if (empty($lpns)): ?>
    <p style="padding:24px;font-family:Arial,sans-serif;color:#64748b;">No hay LPNs generados para esta carga.</p>
<?php else: foreach ($lpns as $lpn): ?>
    <div class="label">
        <img src="/uploads/lpns/<?= htmlspecialchars((string) $lpn->codigo()) ?>.png" alt="Etiqueta <?= htmlspecialchars((string) $lpn->codigo()) ?>">
    </div>
<?php endforeach; endif; ?>
</body>
</html>
