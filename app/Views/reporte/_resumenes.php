<?php
/**
 * Resumenes de salida (gerencia): por cliente, por aduanero y por contenedor (DO).
 * Usan los mismos filtros que el Flujo de cargas; "Salieron" usa el periodo.
 *
 * @var array{cliente: array<int, array<string, mixed>>, aduanero: array<int, array<string, mixed>>, contenedor: array{pendientes: array<int, array<string, mixed>>, salieron: array<int, array<string, mixed>>}} $resumen
 * @var string $resumenActivo
 */
$nf = static fn (float $v, int $d = 0): string => number_format($v, $d, '.', ',');
$pestanas = ['cliente' => 'Por cliente', 'aduanero' => 'Por aduanero', 'contenedor' => 'Por contenedor'];
$doLinks = static fn (array $dos): string => implode(', ', array_map(
    static fn (string $etiqueta, string $id) => '<a href="/salidas/' . htmlspecialchars($id) . '" class="text-amber-600 hover:underline whitespace-nowrap">' . htmlspecialchars($etiqueta) . '</a>',
    array_keys($dos),
    array_values($dos),
)) ?: '—';
$th = 'py-2 pr-3';
$thn = 'py-2 pr-3 text-right';
$tdn = 'py-2 pr-3 text-right whitespace-nowrap';
?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6" id="resumenes-salida" data-vivo-region="global">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <h2 class="font-semibold text-black flex items-center gap-2"><?= icon_small('chart', 'w-4 h-4 text-amber-600') ?> Resumen de salidas</h2>
        <a href="#" id="resumen-excel" class="inline-flex items-center gap-2 bg-emerald-600 text-white rounded-lg px-3 py-1.5 text-sm font-medium hover:bg-emerald-500 transition">
            <?= icon_small('download') ?><span>Excel de este resumen</span>
        </a>
    </div>

    <div class="mb-4 inline-flex rounded-lg border border-zinc-300 p-1 bg-zinc-50 text-sm" role="tablist">
        <?php foreach ($pestanas as $clave => $texto): ?>
            <button type="button" data-resumen-tab="<?= $clave ?>"
                    class="px-4 py-1.5 rounded-md font-medium transition <?= $clave === $resumenActivo ? 'bg-white shadow text-zinc-900' : 'text-zinc-500 hover:text-zinc-800' ?>">
                <?= htmlspecialchars($texto) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach (['cliente' => 'Cliente', 'aduanero' => 'Aduanero'] as $clave => $titulo): $filas = $resumen[$clave]; ?>
        <div data-resumen-panel="<?= $clave ?>" class="<?= $clave === $resumenActivo ? '' : 'hidden' ?>">
            <p class="text-xs text-zinc-500 mb-2">Bultos de cada <?= mb_strtolower($titulo) ?> segun donde estan hoy. Kg, p3 y valor son de lo que esta en salida (OS + contenedor). "Salieron" es del periodo elegido.</p>
            <?php if ($filas === []): ?>
                <p class="text-sm text-zinc-500 py-4">Sin datos con estos filtros.</p>
            <?php else: $t = ['cargas' => 0, 'bodega' => 0, 'os' => 0, 'do' => 0, 'kg' => 0.0, 'p3' => 0.0, 'valor' => 0.0, 'salieron' => 0]; ?>
            <div class="overflow-x-auto max-h-[32rem] overflow-y-auto border border-slate-200 rounded-lg">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                            <th class="py-2 px-3"><?= $titulo ?></th>
                            <?php if ($clave === 'aduanero'): ?><th class="<?= $thn ?>">Clientes</th><?php endif; ?>
                            <th class="<?= $thn ?>">Cargas</th>
                            <th class="<?= $thn ?>" title="Bultos en bodega sin orden de salida">En bodega</th>
                            <th class="<?= $thn ?>" title="Bultos en una OS (orden de salida) sin DO">En OS</th>
                            <th class="<?= $thn ?>" title="Bultos en un DO pendiente (armando el contenedor)">En contenedor</th>
                            <th class="<?= $thn ?>">Kg en salida</th>
                            <th class="<?= $thn ?>">P3 en salida</th>
                            <th class="<?= $thn ?>">Valor en salida</th>
                            <th class="<?= $thn ?>" title="Bultos despachados en el periodo">Salieron</th>
                            <th class="<?= $th ?>">OS / DO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $g): foreach ($t as $k => $_) { $t[$k] += $g[$k]; } ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="py-2 px-3 text-black font-medium"><?= htmlspecialchars($g['nombre']) ?></td>
                                <?php if ($clave === 'aduanero'): ?><td class="<?= $tdn ?> text-zinc-700" title="<?= htmlspecialchars(implode(', ', $g['clientesLista'])) ?>"><?= $g['clientes'] ?></td><?php endif; ?>
                                <td class="<?= $tdn ?> text-zinc-700"><?= $g['cargas'] ?></td>
                                <td class="<?= $tdn ?> text-zinc-700"><?= $g['bodega'] ? $nf($g['bodega']) : '—' ?></td>
                                <td class="<?= $tdn ?> text-sky-700"><?= $g['os'] ? $nf($g['os']) : '—' ?></td>
                                <td class="<?= $tdn ?> text-blue-700 font-medium"><?= $g['do'] ? $nf($g['do']) : '—' ?></td>
                                <td class="<?= $tdn ?> text-zinc-700"><?= $g['os'] + $g['do'] ? $nf($g['kg'], 1) : '—' ?></td>
                                <td class="<?= $tdn ?> text-zinc-700"><?= $g['os'] + $g['do'] ? $nf($g['p3'], 2) : '—' ?></td>
                                <td class="<?= $tdn ?> text-zinc-700"><?= $g['os'] + $g['do'] ? $nf($g['valor'], 2) : '—' ?></td>
                                <td class="<?= $tdn ?> text-violet-700"><?= $g['salieron'] ? $nf($g['salieron']) : '—' ?></td>
                                <td class="<?= $th ?> text-xs"><?= $doLinks($g['dos']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="sticky bottom-0 bg-slate-100 font-semibold text-black">
                        <tr>
                            <td class="py-2 px-3">Totales (<?= count($filas) ?>)</td>
                            <?php if ($clave === 'aduanero'): ?><td></td><?php endif; ?>
                            <td class="<?= $tdn ?>"><?= $nf($t['cargas']) ?></td>
                            <td class="<?= $tdn ?>"><?= $nf($t['bodega']) ?></td>
                            <td class="<?= $tdn ?>"><?= $nf($t['os']) ?></td>
                            <td class="<?= $tdn ?>"><?= $nf($t['do']) ?></td>
                            <td class="<?= $tdn ?>"><?= $nf($t['kg'], 1) ?></td>
                            <td class="<?= $tdn ?>"><?= $nf($t['p3'], 2) ?></td>
                            <td class="<?= $tdn ?>"><?= $nf($t['valor'], 2) ?></td>
                            <td class="<?= $tdn ?>"><?= $nf($t['salieron']) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div data-resumen-panel="contenedor" class="<?= $resumenActivo === 'contenedor' ? '' : 'hidden' ?> space-y-6">
        <?php foreach (['pendientes' => 'En salida (DO pendientes, armando el contenedor)', 'salieron' => 'Salieron en el periodo'] as $tipo => $titulo): $dos = $resumen['contenedor'][$tipo]; ?>
            <div>
                <h3 class="text-sm font-semibold text-black mb-2"><?= htmlspecialchars($titulo) ?> · <?= count($dos) ?></h3>
                <?php if ($dos === []): ?>
                    <p class="text-sm text-zinc-500">Ninguno.</p>
                <?php else: $t = ['cargas' => 0, 'bultos' => 0, 'escaneados' => 0, 'kg' => 0.0, 'p3' => 0.0, 'valor' => 0.0]; ?>
                <div class="overflow-x-auto border border-slate-200 rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                                <th class="py-2 px-3">DO</th>
                                <th class="<?= $th ?>">Contenedor</th>
                                <th class="<?= $th ?>">Naviera / barco</th>
                                <th class="<?= $th ?>">Destino</th>
                                <th class="<?= $th ?>"><?= $tipo === 'salieron' ? 'Confirmada' : 'ETD' ?></th>
                                <th class="<?= $th ?>">Aduaneros</th>
                                <th class="<?= $thn ?>">Clientes</th>
                                <th class="<?= $thn ?>">Cargas</th>
                                <th class="<?= $thn ?>">Bultos</th>
                                <th class="<?= $thn ?>">Kg</th>
                                <th class="<?= $thn ?>">P3</th>
                                <th class="<?= $thn ?>">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dos as $d): foreach ($t as $k => $_) { $t[$k] += $d[$k]; } $completo = $d['escaneados'] >= $d['bultos']; ?>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="py-2 px-3 whitespace-nowrap"><a href="/salidas/<?= htmlspecialchars($d['id']) ?>" class="text-amber-600 hover:underline font-medium"><?= htmlspecialchars($d['etiqueta']) ?></a></td>
                                    <td class="<?= $th ?> font-mono text-zinc-700 whitespace-nowrap"><?= htmlspecialchars($d['contenedor'] ?? '—') ?></td>
                                    <td class="<?= $th ?> text-zinc-700"><?= htmlspecialchars($d['naviera'] ?? '—') ?><?php if ($d['barco']): ?><span class="block text-xs text-zinc-500"><?= htmlspecialchars($d['barco']) ?></span><?php endif; ?></td>
                                    <td class="<?= $th ?> text-zinc-700"><?= htmlspecialchars($d['destino'] ?? '—') ?></td>
                                    <td class="<?= $th ?> text-zinc-700 whitespace-nowrap"><?= htmlspecialchars(($tipo === 'salieron' ? $d['confirmada']?->format('d/m/Y') : $d['etd']?->format('d/m/Y')) ?? '—') ?></td>
                                    <td class="<?= $th ?> text-zinc-700"><?= htmlspecialchars(implode(', ', $d['aduanerosLista'])) ?></td>
                                    <td class="<?= $tdn ?> text-zinc-700" title="<?= htmlspecialchars(implode(', ', $d['clientesLista'])) ?>"><?= $d['clientes'] ?></td>
                                    <td class="<?= $tdn ?> text-zinc-700"><?= $d['cargas'] ?></td>
                                    <td class="<?= $tdn ?>">
                                        <?php if ($tipo === 'pendientes'): ?>
                                            <span class="<?= $completo ? 'text-emerald-700 font-medium' : 'text-black' ?>" title="Escaneados de bultos"><?= $d['escaneados'] ?> / <?= $d['bultos'] ?></span>
                                        <?php else: ?>
                                            <?= $nf($d['bultos']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= $tdn ?> text-zinc-700"><?= $nf($d['kg'], 1) ?></td>
                                    <td class="<?= $tdn ?> text-zinc-700"><?= $nf($d['p3'], 2) ?></td>
                                    <td class="<?= $tdn ?> text-zinc-700"><?= $nf($d['valor'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-slate-100 font-semibold text-black">
                            <tr>
                                <td class="py-2 px-3" colspan="7">Totales</td>
                                <td class="<?= $tdn ?>"><?= $nf($t['cargas']) ?></td>
                                <td class="<?= $tdn ?>"><?= $tipo === 'pendientes' ? $nf($t['escaneados']) . ' / ' . $nf($t['bultos']) : $nf($t['bultos']) ?></td>
                                <td class="<?= $tdn ?>"><?= $nf($t['kg'], 1) ?></td>
                                <td class="<?= $tdn ?>"><?= $nf($t['p3'], 2) ?></td>
                                <td class="<?= $tdn ?>"><?= $nf($t['valor'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
