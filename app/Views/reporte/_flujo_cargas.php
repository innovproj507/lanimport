<?php
/**
 * Flujo de cargas (gerencia): Llegando -> En deposito -> En salida -> Salieron.
 * Las tarjetas son pestañas: al pulsar una se ve su lista. Se actualiza solo ("En vivo").
 *
 * @var array{etapas: array<string, array{cargas: int, bultos: float, peso: float, p3: float, filas: array<int, array<string, mixed>>}>} $flujo
 * @var string $etapaActiva
 */
use Courier\Reporte\Application\ObtenerFlujoCargas\ObtenerFlujoCargasHandler;

$nf = static fn (float $v, int $d = 0): string => number_format($v, $d, '.', ',');
$tarjetas = [
    'llegando' => ['color' => 'amber', 'icono' => 'receipt', 'ayuda' => 'Registradas, con bultos por escanear en recepcion'],
    'deposito' => ['color' => 'emerald', 'icono' => 'box', 'ayuda' => 'Recibidas en bodega, sin salida asignada'],
    'salida' => ['color' => 'blue', 'icono' => 'truck', 'ayuda' => 'En una DO pendiente (armando el contenedor)'],
    'salieron' => ['color' => 'violet', 'icono' => 'check', 'ayuda' => 'Despachadas en el periodo elegido'],
];
$doLink = static fn (array $d): string => '<a href="/salidas/' . htmlspecialchars($d['id']) . '" class="text-amber-600 hover:underline whitespace-nowrap">'
    . htmlspecialchars($d['etiqueta']) . '</a>';
?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6" id="flujo-cargas" data-vivo-region="global">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <h2 class="font-semibold text-black flex items-center gap-2"><?= icon_small('truck', 'w-4 h-4 text-amber-600') ?> Flujo de cargas</h2>
        <a href="#" id="flujo-excel" class="inline-flex items-center gap-2 bg-emerald-600 text-white rounded-lg px-3 py-1.5 text-sm font-medium hover:bg-emerald-500 transition">
            <?= icon_small('download') ?><span>Excel de esta etapa</span>
        </a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <?php $paso = 0; foreach (ObtenerFlujoCargasHandler::ETAPAS as $clave => $titulo): $t = $tarjetas[$clave]; $e = $flujo['etapas'][$clave]; $paso++; ?>
            <button type="button" data-flujo-etapa="<?= $clave ?>" title="<?= htmlspecialchars($t['ayuda']) ?>"
                    class="flujo-tarjeta text-left rounded-xl border-2 p-4 transition border-<?= $t['color'] ?>-200 hover:border-<?= $t['color'] ?>-400 <?= $clave === $etapaActiva ? 'ring-2 ring-' . $t['color'] . '-400 bg-' . $t['color'] . '-50' : 'bg-white' ?>"
                    data-color="<?= $t['color'] ?>">
                <p class="text-xs font-semibold uppercase tracking-wide text-<?= $t['color'] ?>-700 flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-<?= $t['color'] ?>-500 text-white flex items-center justify-center text-[11px]"><?= $paso ?></span>
                    <?= htmlspecialchars($titulo) ?>
                </p>
                <p class="text-3xl font-bold text-black mt-2"><?= $e['cargas'] ?> <span class="text-sm font-normal text-zinc-500">cargas</span></p>
                <p class="text-xs text-zinc-600 mt-1"><?= $nf($e['bultos']) ?> bultos · <?= $nf($e['peso'], 1) ?> kg · <?= $nf($e['p3'], 1) ?> p3</p>
                <p class="text-[11px] text-zinc-400 mt-1"><?= htmlspecialchars($t['ayuda']) ?></p>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach (ObtenerFlujoCargasHandler::ETAPAS as $clave => $titulo): $e = $flujo['etapas'][$clave]; ?>
        <div data-flujo-panel="<?= $clave ?>" class="<?= $clave === $etapaActiva ? '' : 'hidden' ?>">
            <?php if ($e['filas'] === []): ?>
                <p class="text-sm text-zinc-500 py-4">No hay cargas en "<?= htmlspecialchars($titulo) ?>"<?= $clave === 'salieron' ? ' en este periodo' : '' ?>.</p>
            <?php else: ?>
            <div class="overflow-x-auto max-h-[32rem] overflow-y-auto border border-slate-200 rounded-lg">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                            <th class="py-2 px-3">Tracking</th>
                            <th class="py-2 pr-3">Cliente</th>
                            <th class="py-2 pr-3">Aduanero</th>
                            <th class="py-2 pr-3">Ingreso</th>
                            <th class="py-2 pr-3 text-right">Bultos</th>
                            <th class="py-2 pr-3 text-right">Peso</th>
                            <th class="py-2 pr-3 text-right">P3</th>
                            <?php if ($clave === 'llegando'): ?>
                                <th class="py-2 pr-3">Escaneados</th><th class="py-2 pr-3 text-right">Hace</th>
                            <?php elseif ($clave === 'deposito'): ?>
                                <th class="py-2 pr-3 text-right">Dias en bodega</th>
                            <?php elseif ($clave === 'salida'): ?>
                                <th class="py-2 pr-3">DO</th><th class="py-2 pr-3">Contenedor</th><th class="py-2 pr-3">Escaneados para salida</th>
                            <?php else: ?>
                                <th class="py-2 pr-3">Salio</th><th class="py-2 pr-3">DO</th><th class="py-2 pr-3">Contenedor</th><th class="py-2 pr-3">Naviera</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($e['filas'] as $f): ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="py-2 px-3 font-mono whitespace-nowrap"><a href="/cargas/<?= htmlspecialchars($f['id']) ?>" class="text-amber-600 hover:underline"><?= htmlspecialchars($f['tracking']) ?></a></td>
                                <td class="py-2 pr-3 text-zinc-700"><?= htmlspecialchars($f['cliente']) ?></td>
                                <td class="py-2 pr-3 text-zinc-700"><?= htmlspecialchars($f['aduanero']) ?></td>
                                <td class="py-2 pr-3 text-zinc-500 whitespace-nowrap"><?= htmlspecialchars($f['fechaIngreso']?->format('d/m/Y') ?? '—') ?></td>
                                <td class="py-2 pr-3 text-right text-black"><?= $nf($f['bultos']) ?></td>
                                <td class="py-2 pr-3 text-right text-zinc-700 whitespace-nowrap"><?= $nf($f['peso'], 2) ?></td>
                                <td class="py-2 pr-3 text-right text-zinc-700 whitespace-nowrap"><?= $nf($f['p3'], 2) ?></td>
                                <?php if ($clave === 'llegando'): ?>
                                    <td class="py-2 pr-3 whitespace-nowrap">
                                        <?php if ($f['etiquetas'] === 0): ?>
                                            <?= badge('Sin etiquetas', 'slate') ?>
                                        <?php else: $listos = $f['etiquetas'] - $f['porEscanear']; ?>
                                            <span class="text-black font-medium"><?= $listos ?></span> <span class="text-zinc-500">de <?= $f['etiquetas'] ?></span>
                                            <div class="w-24 bg-slate-200 rounded-full h-1.5 mt-1"><div class="bg-amber-500 h-1.5 rounded-full" style="width: <?= (int) round($listos * 100 / max(1, $f['etiquetas'])) ?>%"></div></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 pr-3 text-right whitespace-nowrap <?= $f['horas'] >= 24 ? 'text-red-600 font-medium' : 'text-zinc-600' ?>">
                                        <?= $f['horas'] >= 48 ? intdiv($f['horas'], 24) . ' dias' : $f['horas'] . ' h' ?>
                                    </td>
                                <?php elseif ($clave === 'deposito'): ?>
                                    <td class="py-2 pr-3 text-right whitespace-nowrap">
                                        <span class="<?= $f['dias'] > 7 ? 'text-red-600 font-semibold' : 'text-zinc-700' ?>"><?= $f['dias'] ?></span>
                                        <?php if ($f['despachados'] > 0): ?><span class="block text-xs text-violet-700">Parcial: <?= $f['despachados'] ?> ya salieron</span><?php endif; ?>
                                    </td>
                                <?php elseif ($clave === 'salida'): ?>
                                    <td class="py-2 pr-3"><?= implode(', ', array_map($doLink, $f['dos'])) ?></td>
                                    <td class="py-2 pr-3 font-mono text-zinc-700 whitespace-nowrap"><?= htmlspecialchars(implode(', ', array_unique(array_filter(array_column($f['dos'], 'contenedor')))) ?: '—') ?></td>
                                    <td class="py-2 pr-3 whitespace-nowrap">
                                        <?php $completo = $f['escaneadosDo'] >= $f['enDo']; ?>
                                        <span class="<?= $completo ? 'text-emerald-700 font-medium' : 'text-black' ?>"><?= $f['escaneadosDo'] ?> de <?= $f['enDo'] ?></span>
                                        <?php if ($f['enDo'] < $f['bultos'] || $f['despachados'] > 0): ?><span class="block text-xs text-violet-700">Parcial: <?= $f['enDo'] ?> de <?= $nf($f['bultos']) ?> en esta salida</span><?php endif; ?>
                                    </td>
                                <?php else: ?>
                                    <td class="py-2 pr-3 whitespace-nowrap text-zinc-700"><?= htmlspecialchars($f['fechaSalida']->format('d/m/Y')) ?></td>
                                    <td class="py-2 pr-3"><?= $f['dos'] !== [] ? implode(', ', array_map($doLink, $f['dos'])) : '—' ?></td>
                                    <td class="py-2 pr-3 font-mono text-zinc-700 whitespace-nowrap"><?= htmlspecialchars(implode(', ', array_unique(array_filter(array_column($f['dos'], 'contenedor')))) ?: '—') ?></td>
                                    <td class="py-2 pr-3 text-zinc-700"><?= htmlspecialchars($f['naviera'] ?? '—') ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
