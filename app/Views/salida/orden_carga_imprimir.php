<?php

declare(strict_types=1);

/**
 * "Orden de carga": trafico la imprime y la entrega a bodega. Dice que contenedor se carga y,
 * por carga, las etiquetas exactas (LPN) que suben, con su ubicacion, para buscarlas y escanearlas.
 */
/** @var \Courier\Salida\Domain\SalidaOrden $orden */
/** @var array<int, array{tracking: string, cliente: string, marca: ?string, totalCarga: int, bultos: array<int, array{codigo: string, numero: ?int, ubicacion: ?string, escaneado: bool}>}> $grupos */
/** @var \Courier\Configuracion\Domain\DatosEmpresa $empresa */
/** @var string|null $usuario quien armo la salida */

$e = static fn (?string $t): string => htmlspecialchars($t ?? '');
$embarque = $orden->embarque();
$totalBultos = array_sum(array_map(static fn (array $g) => count($g['bultos']), $grupos));
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de carga <?= $e($orden->numeroDo()) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #fff; color: #111; }
        .print-bar { background: #1a1d27; color: #f1f5f9; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-family: 'Segoe UI', Arial, sans-serif; }
        .print-bar h2 { font-size: 14px; }
        .btn-back { color: #94a3b8; font-size: 13px; text-decoration: none; padding: 8px 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,.15); }
        .btn-print { background: #059669; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .hoja { width: 20cm; max-width: calc(100% - 24px); margin: 1cm auto; font-size: 12px; }
        .encabezado { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 10px; }
        .encabezado img { height: 56px; }
        .encabezado h1 { font-size: 17px; text-align: right; }
        .encabezado .do { font-size: 22px; font-weight: 700; text-align: right; }
        .datos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px 16px; margin-bottom: 12px; font-size: 12px; }
        .datos b { display: inline-block; min-width: 78px; }
        .resumen { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px 10px; margin-bottom: 12px; font-weight: 700; }
        .carga { border: 1px solid #333; margin-bottom: 12px; break-inside: avoid; }
        .carga-titulo { background: #e5e7eb; padding: 6px 10px; display: flex; justify-content: space-between; gap: 10px; font-weight: 700; }
        .carga-titulo .tracking { font-family: 'Courier New', monospace; font-size: 14px; }
        .bultos { display: grid; grid-template-columns: repeat(3, 1fr); }
        .bulto { display: flex; align-items: center; gap: 6px; padding: 4px 8px; border-top: 1px solid #ddd; border-right: 1px solid #ddd; font-size: 11px; }
        .caja { width: 13px; height: 13px; border: 1.5px solid #111; flex-shrink: 0; text-align: center; line-height: 11px; font-size: 10px; }
        .bulto .codigo { font-family: 'Courier New', monospace; font-weight: 700; }
        .bulto .n { color: #444; }
        .bulto .ubic { margin-left: auto; color: #555; font-size: 10px; }
        .firmas { display: grid; grid-template-columns: repeat(2, 1fr); gap: 40px; margin-top: 40px; }
        .firma { border-top: 1px solid #111; padding-top: 4px; text-align: center; font-size: 11px; }
        .meta { font-size: 10px; color: #555; margin-top: 16px; text-align: right; }
        @media print {
            @page { size: letter portrait; margin: 1cm; }
            .print-bar { display: none !important; }
            .hoja { margin: 0 auto; max-width: none; width: auto; }
            .carga-titulo, .resumen { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="print-bar">
    <h2>Orden de carga para bodega · <?= $e($orden->etiquetaDo()) ?></h2>
    <div style="display:flex; gap:8px;">
        <a href="/salidas/<?= $e($orden->id()) ?>" class="btn-back">Volver</a>
        <button type="button" class="btn-print" onclick="window.print()">Imprimir</button>
    </div>
</div>

<div class="hoja">
    <div class="encabezado">
        <img src="/assets/images/logo.svg" alt="<?= $e($empresa->nombre) ?>">
        <div>
            <h1>ORDEN DE CARGA PARA BODEGA</h1>
            <div class="do"><?= $e($orden->etiquetaDo()) ?></div>
        </div>
    </div>

    <div class="datos">
        <div><b>Contenedor:</b> <?= $e($embarque->contenedorFormateado() ?? '—') ?></div>
        <div><b>Sello:</b> <?= $e($embarque->numeroSello ?? '—') ?></div>
        <div><b>Naviera:</b> <?= $e($embarque->navieraNombre ?? '—') ?></div>
        <div><b>Fecha cargue:</b> <?= $e($embarque->fechaCargue?->format('d/m/Y') ?? '—') ?></div>
        <div><b>Destino:</b> <?= $e($embarque->destinoFinal ? mb_strtoupper($embarque->destinoFinal) : '—') ?></div>
        <div><b>Elaborado:</b> <?= $e($usuario ?? '—') ?> · <?= $e($orden->createdAt()->format('d/m/Y H:i')) ?></div>
    </div>

    <div class="resumen">
        Subir al contenedor: <?= $totalBultos ?> bultos de <?= count($grupos) ?> carga(s). Marque cada etiqueta al escanearla.
    </div>

    <?php foreach ($grupos as $g): ?>
        <div class="carga">
            <div class="carga-titulo">
                <span><span class="tracking"><?= $e($g['tracking']) ?></span> · <?= $e(mb_strtoupper($g['cliente'])) ?><?= $g['marca'] ? ' · ' . $e(mb_strtoupper($g['marca'])) : '' ?></span>
                <span><?= count($g['bultos']) ?><?= $g['totalCarga'] > count($g['bultos']) ? ' de ' . $g['totalCarga'] : '' ?> bultos</span>
            </div>
            <div class="bultos">
                <?php foreach ($g['bultos'] as $b): ?>
                    <div class="bulto">
                        <span class="caja"><?= $b['escaneado'] ? '✓' : '' ?></span>
                        <span class="codigo"><?= $e($b['codigo']) ?></span>
                        <?php if ($b['numero'] !== null && $g['totalCarga'] > 0): ?><span class="n"><?= $b['numero'] ?>/<?= $g['totalCarga'] ?></span><?php endif; ?>
                        <span class="ubic"><?= $e($b['ubicacion'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="firmas">
        <div class="firma">Entregado por (Trafico)</div>
        <div class="firma">Cargado por (Bodega) · Fecha y hora</div>
    </div>

    <p class="meta">Generado por el sistema el <?= $e((new DateTimeImmutable())->format('d/m/Y H:i')) ?> · Las casillas con ✓ ya estaban escaneadas al imprimir.</p>
</div>
</body>
</html>
