<?php

declare(strict_types=1);

/** @var \Courier\Carga\Domain\Carga|null $carga */
/** @var \Courier\Carga\Domain\ActaRecepcion $acta */
/** @var \Courier\Cliente\Domain\Cliente|null $cliente */
/** @var string|null $verificadoPor */
/** @var \Courier\Configuracion\Domain\DatosEmpresa $empresa */

$e = static fn (?string $texto): string => htmlspecialchars($texto ?? '');
$tipos = $acta->tiposMercancia();
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acta <?= $e($acta->codigo()) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #fff; color: #111; }
        .print-bar {
            background: #1a1d27; color: #f1f5f9; padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            font-family: 'Segoe UI', Arial, sans-serif; flex-wrap: wrap;
        }
        .print-bar h2 { font-size: 14px; font-weight: 700; }
        .btn-back {
            color: #94a3b8; font-size: 13px; text-decoration: none;
            padding: 8px 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,.15);
        }
        .btn-print {
            background: #059669; color: #fff; border: none; padding: 10px 22px;
            border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .hoja { width: 19cm; max-width: calc(100% - 24px); margin: 1cm auto; font-size: 13px; }
        @media screen and (max-width: 640px) {
            .hoja { margin: 12px auto; font-size: 12px; }
            .fila { flex-wrap: wrap; }
            .campo, .marca { flex: 1 1 100% !important; width: auto; }
            .tipos { flex-wrap: wrap; }
        }
        .encabezado { text-align: center; margin-bottom: 18px; }
        .encabezado img { height: 60px; }
        .encabezado p { font-size: 12px; line-height: 1.4; }
        .encabezado h1 { font-size: 16px; margin-top: 10px; letter-spacing: 1px; }
        .fila { display: flex; gap: 16px; margin-bottom: 12px; align-items: flex-end; }
        .campo { flex: 1; display: flex; align-items: flex-end; gap: 8px; }
        .campo label { font-weight: 700; text-transform: uppercase; font-size: 11px; white-space: nowrap; }
        .campo span { flex: 1; border-bottom: 1px solid #333; min-height: 18px; padding: 0 4px 2px; }
        .marca { border: 1px solid #333; width: 6cm; min-height: 2cm; padding: 6px; text-align: center; }
        .marca label { display: block; font-weight: 700; font-size: 11px; margin-bottom: 6px; }
        .tipos { display: flex; border: 1px solid #333; }
        .tipos div { padding: 3px 12px; border-right: 1px solid #333; font-size: 11px; }
        .tipos div:last-child { border-right: none; }
        .tipos .x { font-weight: 700; }
        .descripcion { border-bottom: 1px solid #333; min-height: 60px; padding: 4px; white-space: pre-line; margin-bottom: 24px; }
        .firmas { margin-top: 36px; }
        .firmas .campo span { min-height: 40px; display: flex; align-items: flex-end; }
        .meta { font-size: 10px; color: #555; margin-top: 24px; text-align: right; }
        @media print {
            @page { size: letter; margin: 1cm; }
            .print-bar { display: none !important; }
            .hoja { margin: 0 auto; max-width: none; }
        }
    </style>
</head>
<body>
<div class="print-bar">
    <div>
        <h2>Acta de recepcion <?= $e($acta->codigo()) ?></h2>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="/recepciones/<?= $e($acta->id()) ?>" class="btn-back">Volver</a>
        <button type="button" class="btn-print" onclick="window.print()">Imprimir</button>
    </div>
</div>

<div class="hoja">
    <div class="encabezado">
        <img src="/assets/images/logo.svg" alt="<?= $e($empresa->nombre) ?>">
        <p>
            <?php if ($empresa->ruc): ?>R.U.C. <?= $e($empresa->ruc) ?><?= $empresa->dv ? ' D.V.' . $e($empresa->dv) : '' ?><br><?php endif; ?>
            <?php if ($empresa->claveOperaciones): ?>CLAVE OPERACIONES <?= $e($empresa->claveOperaciones) ?><br><?php endif; ?>
            <?php if ($empresa->direccion): ?><?= $e($empresa->direccion) ?><br><?php endif; ?>
            <?php if ($empresa->telefono): ?>TEL. <?= $e($empresa->telefono) ?><?php endif; ?>
        </p>
        <h1>ACTA DE RECEPCION DE CARGA</h1>
    </div>

    <div class="fila">
        <div style="flex:1;">
            <div class="fila"><div class="campo"><label>Fecha:</label><span><?= $e($acta->fechaRecepcion()->format('d/m/Y H:i')) ?></span></div></div>
            <div class="fila"><div class="campo"><label>Acta / Tracking:</label><span><?= $e($acta->codigo()) ?><?= $carga !== null ? " / " . $e((string) $carga->trackingNumero()) : "" ?></span></div></div>
            <div class="fila"><div class="campo"><label>Nombre del cliente:</label><span><?= $e($cliente?->nombreCompleto()) ?></span></div></div>
        </div>
        <div class="marca">
            <label>MARCA</label>
            <?= $e($acta->marca()) ?>
        </div>
    </div>

    <div class="fila"><div class="campo"><label>Nombre de la empresa:</label><span><?= $e($acta->empresaTransporte()) ?></span></div></div>
    <div class="fila">
        <div class="campo"><label>Nombre del chofer:</label><span><?= $e($acta->choferNombre()) ?></span></div>
        <div class="campo" style="flex:0 0 6cm;"><label>Placa:</label><span><?= $e($acta->placa()) ?></span></div>
    </div>
    <div class="fila"><div class="campo"><label>Cantidad de bultos:</label><span><?= $acta->cantidadBultos() ?></span></div></div>
    <div class="fila"><div class="campo"><label>Cantidad de rollos:</label><span><?= $acta->cantidadRollos() ?></span></div></div>
    <div class="fila"><div class="campo"><label>Total recibido:</label><span><strong><?= $acta->totalRecibido() ?></strong></span></div></div>

    <div class="fila">
        <div class="campo" style="flex:0 0 auto;"><label>Mercancia fisicamente descripcion:</label></div>
        <div class="tipos">
            <?php foreach (['cajas' => 'CAJAS', 'saco' => 'SACO', 'bultos' => 'BULTOS'] as $tipo => $etiqueta): ?>
                <div class="<?= in_array($tipo, $tipos, true) ? 'x' : '' ?>"><?= in_array($tipo, $tipos, true) ? '[X] ' : '[ ] ' ?><?= $etiqueta ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="descripcion"><?= $e($acta->descripcionMercancia()) ?></div>

    <div class="firmas">
        <div class="fila">
            <div class="campo"><label>Entregado por:</label><span><?= $e($acta->entregadoPor()) ?></span></div>
            <div class="campo" style="flex:0 0 7cm;"><label>Cedula:</label><span><?= $e($acta->entregadoPorCedula()) ?></span></div>
        </div>
        <div class="fila">
            <div class="campo"><label>Firma:</label><span></span></div>
        </div>
        <div class="fila">
            <div class="campo"><label>Verificado por:</label><span><?= $e($verificadoPor) ?></span></div>
            <div class="campo" style="flex:0 0 7cm;"><label>Firma:</label><span></span></div>
        </div>
    </div>

    <p class="meta">Generado por el sistema el <?= $e((new DateTimeImmutable())->format('d/m/Y H:i')) ?></p>
</div>
</body>
</html>
