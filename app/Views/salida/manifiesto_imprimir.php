<?php

declare(strict_types=1);

/** @var \Courier\Salida\Domain\SalidaOrden $orden */
/** @var \Courier\Cliente\Domain\Cliente|null $cliente */
/** @var array<int, array{cliente: string, marca: string, compania: string, tracking: ?string, bultos: int, kilos: float, m3: float, valor: float}> $filas */
/** @var \Courier\Configuracion\Domain\DatosEmpresa $empresa */

$e = static fn (?string $t): string => htmlspecialchars($t ?? '');
$n = static fn (float $v, int $dec = 2): string => number_format($v, $dec, '.', ',');
$embarque = $orden->embarque();
$meses = [1 => 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
$fechaLarga = static fn (?DateTimeImmutable $f): string => $f !== null ? $f->format('d') . ' DE ' . $meses[(int) $f->format('n')] . ' ' . $f->format('Y') : '';
// "FECHA" del manifiesto: la de cargue; si aun no se registra, la de creacion de la salida.
$fecha = $embarque->fechaCargue ?? $orden->createdAt();
$totales = ['bultos' => 0, 'kilos' => 0.0, 'm3' => 0.0, 'valor' => 0.0];
foreach ($filas as $f) {
    foreach ($totales as $k => $_) {
        $totales[$k] += $f[$k];
    }
}
$filasVacias = max(0, 8 - count($filas));
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifiesto <?= $e($orden->numeroDo()) ?> - <?= $e($cliente?->nombreCompleto()) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #fff; color: #111; }
        .print-bar { background: #1a1d27; color: #f1f5f9; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-family: 'Segoe UI', Arial, sans-serif; }
        .print-bar h2 { font-size: 14px; }
        .btn-back { color: #94a3b8; font-size: 13px; text-decoration: none; padding: 8px 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,.15); }
        .btn-print { background: #059669; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .hoja { width: 26cm; max-width: calc(100% - 24px); margin: 1cm auto; font-size: 12px; }
        .encabezado { text-align: center; margin-bottom: 10px; }
        .encabezado img { height: 70px; }
        .encabezado h1 { font-size: 16px; margin-top: 6px; }
        .datos { text-align: center; font-weight: 700; font-size: 12px; margin: 4px 0 2px; }
        .datos2 { text-align: center; font-size: 11px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 6px; }
        th { background: #e5e7eb; font-size: 11px; }
        td.n, th.n { text-align: right; }
        tfoot td { font-weight: 700; }
        .vacia td { height: 20px; }
        .meta { font-size: 10px; color: #555; margin-top: 10px; text-align: right; }
        @media print {
            @page { size: letter landscape; margin: 1cm; }
            .print-bar { display: none !important; }
            .hoja { margin: 0 auto; max-width: none; width: auto; }
            th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="print-bar">
    <h2>Manifiesto <?= $e($orden->etiquetaDo()) ?></h2>
    <div style="display:flex; gap:8px;">
        <a href="/salidas/<?= $e($orden->id()) ?>" class="btn-back">Volver</a>
        <button type="button" class="btn-print" onclick="window.print()">Imprimir</button>
    </div>
</div>

<div class="hoja">
    <div class="encabezado">
        <img src="/assets/images/logo.svg" alt="<?= $e($empresa->nombre) ?>">
        <h1><?= $e(mb_strtoupper($cliente?->nombreCompleto() ?? '')) ?> (<?= $e($orden->numeroDo()) ?>)</h1>
    </div>
    <p class="datos">
        FECHA: <?= $e($fechaLarga($fecha)) ?>
        <?php if ($embarque->numeroContenedor): ?>&nbsp; CONTENEDOR: <?= $e($embarque->contenedorFormateado()) ?><?php endif; ?>
        <?php if ($embarque->numeroSello): ?>&nbsp; SELLO: <?= $e($embarque->numeroSello) ?><?php endif; ?>
        <?php if ($embarque->navieraNombre): ?>&nbsp; NAVIERA: <?= $e($embarque->navieraNombre) ?><?php endif; ?>
    </p>
    <p class="datos2">
        <?php if ($embarque->nombreBarco): ?>BARCO: <?= $e($embarque->nombreBarco) ?> &nbsp;<?php endif; ?>
        <?php if ($embarque->fechaSalidaEtd): ?>ETD: <?= $e($embarque->fechaSalidaEtd->format('d/m/Y')) ?> &nbsp;<?php endif; ?>
        <?php if ($embarque->destinoFinal): ?>DESTINO FINAL: <?= $e(mb_strtoupper($embarque->destinoFinal)) ?><?php endif; ?>
    </p>

    <table>
        <thead>
            <tr>
                <th>NOMBRE</th>
                <th>MARCA</th>
                <th>COMPAÑÍA</th>
                <th class="n">BULTOS</th>
                <th class="n">KILOS</th>
                <th class="n">CUB/MT</th>
                <th class="n">VALOR</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td><?= $e(mb_strtoupper($f['cliente'])) ?></td>
                    <td><?= $e(mb_strtoupper($f['marca'])) ?></td>
                    <td><?= $e(mb_strtoupper($f['compania'])) ?></td>
                    <td class="n"><?= $f['bultos'] ?></td>
                    <td class="n"><?= $n($f['kilos']) ?></td>
                    <td class="n"><?= $f['m3'] > 0 ? $n($f['m3'], 3) : '-' ?></td>
                    <td class="n"><?= $n($f['valor']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $filasVacias; $i++): ?>
                <tr class="vacia"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php endfor; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">TOTALES</td>
                <td class="n"><?= $totales['bultos'] ?></td>
                <td class="n"><?= $n($totales['kilos']) ?></td>
                <td class="n"><?= $totales['m3'] > 0 ? $n($totales['m3'], 3) : '-' ?></td>
                <td class="n"><?= $n($totales['valor']) ?></td>
            </tr>
        </tfoot>
    </table>

    <p class="meta">Generado por el sistema el <?= $e((new DateTimeImmutable())->format('d/m/Y H:i')) ?></p>
</div>
</body>
</html>
