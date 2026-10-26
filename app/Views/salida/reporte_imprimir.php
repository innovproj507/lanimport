<?php

declare(strict_types=1);

/** @var \Courier\Salida\Domain\SalidaOrden $orden */
/** @var array<int, array{cliente: string, marca: string, compania: string, bultos: int, kilos: float, pies3: float, valor: float, fechaIngreso: ?string, aduanero: string, descripcion: string}> $filas */
/** @var array<int, string> $aduaneros los de las cargas de la salida, para filtrar */
/** @var string $aduanero filtro actual ('' = todos) */

$e = static fn (?string $t): string => htmlspecialchars($t ?? '');
$n = static fn (float $v, int $dec = 2): string => number_format($v, $dec, '.', ',');
$embarque = $orden->embarque();
$totales = ['bultos' => 0, 'pies3' => 0.0, 'kilos' => 0.0, 'valor' => 0.0];
foreach ($filas as $f) {
    foreach ($totales as $k => $_) {
        $totales[$k] += $f[$k];
    }
}
$excel = '/salidas/' . $orden->id() . '/reporte/excel' . ($aduanero !== '' ? '?aduanero=' . rawurlencode($aduanero) : '');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de salida <?= $e($orden->etiquetaDo()) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #fff; color: #111; }
        .print-bar { background: #1a1d27; color: #f1f5f9; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-family: 'Segoe UI', Arial, sans-serif; }
        .print-bar h2 { font-size: 14px; }
        .print-bar form { display: flex; align-items: center; gap: 8px; font-size: 13px; }
        .print-bar select { padding: 7px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,.25); background: #fff; color: #111; }
        .acciones { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-back { color: #94a3b8; font-size: 13px; text-decoration: none; padding: 8px 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,.15); }
        .btn-excel { background: #15803d; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .btn-print { background: #2563eb; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .hoja { width: 27cm; max-width: calc(100% - 24px); margin: 1cm auto; font-size: 12px; }
        .titulo { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; margin-bottom: 8px; }
        .titulo h1 { font-size: 15px; }
        .titulo p { font-size: 11px; color: #333; text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 6px; white-space: nowrap; }
        td.texto { white-space: normal; }
        th { background: #fde047; font-size: 11px; text-align: left; }
        td.n, th.n { text-align: right; }
        tfoot td { font-weight: 700; background: #fef08a; }
        .vacio { text-align: center; color: #555; padding: 14px; }
        .meta { font-size: 10px; color: #555; margin-top: 10px; text-align: right; }
        @media print {
            @page { size: letter landscape; margin: 1cm; }
            .print-bar { display: none !important; }
            .hoja { margin: 0 auto; max-width: none; width: auto; }
            th, tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="print-bar">
    <h2>Reporte de salida <?= $e($orden->etiquetaDo()) ?></h2>
    <?php if (count($aduaneros) > 1 || $aduanero !== ''): ?>
        <form method="get">
            <label for="aduanero">Aduanero</label>
            <select id="aduanero" name="aduanero" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach ($aduaneros as $a): ?>
                    <option value="<?= $e($a) ?>" <?= $a === $aduanero ? 'selected' : '' ?>><?= $e(mb_strtoupper($a)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
    <div class="acciones">
        <a href="/salidas/<?= $e($orden->id()) ?>" class="btn-back">Volver</a>
        <a href="<?= $e($excel) ?>" class="btn-excel">Descargar Excel</a>
        <button type="button" class="btn-print" onclick="window.print()">Imprimir</button>
    </div>
</div>

<div class="hoja">
    <div class="titulo">
        <h1>REPORTE DE SALIDA <?= $e($orden->etiquetaDo()) ?><?= $aduanero !== '' ? ' · ADUANERO ' . $e(mb_strtoupper($aduanero)) : '' ?></h1>
        <p>
            <?php if ($embarque->numeroContenedor): ?>CONTENEDOR: <?= $e($embarque->contenedorFormateado()) ?><?php endif; ?>
            <?php if ($embarque->numeroSello): ?> &nbsp; SELLO: <?= $e($embarque->numeroSello) ?><?php endif; ?>
            <?php if ($embarque->destinoFinal): ?><br>DESTINO: <?= $e(mb_strtoupper($embarque->destinoFinal)) ?><?php endif; ?>
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>FECHA</th>
                <th>COMPAÑÍA</th>
                <th>CLIENTE</th>
                <th>MARCA</th>
                <th class="n">BULTOS</th>
                <th class="n">CUBICAJE P3</th>
                <th class="n">PESO</th>
                <th class="n">VALOR</th>
                <th>ADUANERO</th>
                <th>DESCRIPCION</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($filas === []): ?>
                <tr><td colspan="10" class="vacio">No hay cargas en esta salida<?= $aduanero !== '' ? ' para este aduanero' : '' ?>.</td></tr>
            <?php endif; ?>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td><?= $f['fechaIngreso'] !== null ? $e((new DateTimeImmutable($f['fechaIngreso']))->format('m/d/Y')) : '' ?></td>
                    <td class="texto"><?= $e(mb_strtoupper($f['compania'])) ?></td>
                    <td class="texto"><?= $e(mb_strtoupper($f['cliente'])) ?></td>
                    <td class="texto"><?= $e(mb_strtoupper($f['marca'])) ?></td>
                    <td class="n"><?= $f['bultos'] ?></td>
                    <td class="n"><?= $f['pies3'] > 0 ? $n($f['pies3']) : '' ?></td>
                    <td class="n"><?= $f['kilos'] > 0 ? $n($f['kilos']) : '' ?></td>
                    <td class="n"><?= $f['valor'] > 0 ? $n($f['valor']) : '' ?></td>
                    <td class="texto"><?= $e(mb_strtoupper($f['aduanero'])) ?></td>
                    <td class="texto"><?= $e(mb_strtoupper($f['descripcion'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">TOTALES</td>
                <td class="n"><?= $totales['bultos'] ?></td>
                <td class="n"><?= $n($totales['pies3']) ?></td>
                <td class="n"><?= $n($totales['kilos']) ?></td>
                <td class="n"><?= $n($totales['valor']) ?></td>
                <td></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <p class="meta">Generado por el sistema el <?= $e((new DateTimeImmutable())->format('d/m/Y H:i')) ?></p>
</div>
</body>
</html>
