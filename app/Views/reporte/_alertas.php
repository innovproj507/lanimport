<?php
/**
 * Alertas de gerencia: antiguedad en bodega por cliente, OS que esperan DO y contenedores (DO
 * pendientes) con bultos por escanear. Mismos filtros de aduanero y cliente que el flujo.
 *
 * @var array{antiguedad: array<int, array<string, mixed>>, osSinDo: array<int, array<string, mixed>>, incompletos: array<int, array<string, mixed>>} $alertas
 * @var string $alertaActiva
 */
$nf = static fn (float $v, int $d = 0): string => number_format($v, $d, '.', ',');
$pestanasAlerta = [
    'antiguedad' => 'Antiguedad por cliente (' . count($alertas['antiguedad']) . ')',
    'os' => 'OS sin DO (' . count($alertas['osSinDo']) . ')',
    'incompletos' => 'Contenedores incompletos (' . count($alertas['incompletos']) . ')',
];
$hoy = new DateTimeImmutable('today');
$th = 'py-2 pr-3';
$thn = 'py-2 pr-3 text-right';
$tdn = 'py-2 pr-3 text-right whitespace-nowrap';
?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6" id="alertas-gerencia" data-vivo-region="global">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <h2 class="font-semibold text-black flex items-center gap-2"><?= icon_small('clock', 'w-4 h-4 text-red-600') ?> Alertas</h2>
        <a href="#" id="alerta-excel" class="inline-flex items-center gap-2 bg-emerald-600 text-white rounded-lg px-3 py-1.5 text-sm font-medium hover:bg-emerald-500 transition">
            <?= icon_small('download') ?><span>Excel de esta alerta</span>
        </a>
    </div>

    <div class="mb-4 inline-flex flex-wrap rounded-lg border border-zinc-300 p-1 bg-zinc-50 text-sm" role="tablist">
        <?php foreach ($pestanasAlerta as $clave => $texto): ?>
            <button type="button" data-alerta-tab="<?= $clave ?>"
                    class="px-4 py-1.5 rounded-md font-medium transition <?= $clave === $alertaActiva ? 'bg-white shadow text-zinc-900' : 'text-zinc-500 hover:text-zinc-800' ?>">
                <?= htmlspecialchars($texto) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php // ---- Antiguedad: bultos en bodega (sin orden de salida) por rango de dias desde que entraron. ?>
    <div data-alerta-panel="antiguedad" class="<?= $alertaActiva === 'antiguedad' ? '' : 'hidden' ?>">
        <p class="text-xs text-zinc-500 mb-2">Bultos que estan en bodega sin orden de salida, por dias desde que se recibieron. Sirve para almacenaje y para avisar al cliente.</p>
        <?php if ($alertas['antiguedad'] === []): ?>
            <p class="text-sm text-zinc-500 py-4">No hay bultos en bodega con estos filtros.</p>
        <?php else: $t = ['cargas' => 0, 'r15' => 0, 'r30' => 0, 'r60m' => 0, 'r60' => 0, 'total' => 0]; ?>
        <div class="overflow-x-auto max-h-[32rem] overflow-y-auto border border-slate-200 rounded-lg">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-slate-50">
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 px-3">Cliente</th>
                        <th class="<?= $thn ?>">Cargas</th>
                        <th class="<?= $thn ?>">0-15 dias</th>
                        <th class="<?= $thn ?>">16-30 dias</th>
                        <th class="<?= $thn ?>">31-60 dias</th>
                        <th class="<?= $thn ?>">+60 dias</th>
                        <th class="<?= $thn ?>">Total bultos</th>
                        <th class="<?= $th ?>">Carga mas antigua</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alertas['antiguedad'] as $a): foreach ($t as $k => $_) { $t[$k] += $a[$k]; } ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-2 px-3 text-black font-medium"><?= htmlspecialchars($a['nombre']) ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $a['cargas'] ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $a['r15'] ? $nf($a['r15']) : '—' ?></td>
                            <td class="<?= $tdn ?> text-amber-700"><?= $a['r30'] ? $nf($a['r30']) : '—' ?></td>
                            <td class="<?= $tdn ?> text-orange-700 font-medium"><?= $a['r60m'] ? $nf($a['r60m']) : '—' ?></td>
                            <td class="<?= $tdn ?> text-red-700 font-semibold"><?= $a['r60'] ? $nf($a['r60']) : '—' ?></td>
                            <td class="<?= $tdn ?> text-black font-medium"><?= $nf($a['total']) ?></td>
                            <td class="<?= $th ?> whitespace-nowrap">
                                <a href="/cargas/<?= htmlspecialchars($a['masVieja']['id']) ?>" class="font-mono text-amber-600 hover:underline"><?= htmlspecialchars($a['masVieja']['tracking']) ?></a>
                                <span class="text-xs <?= $a['diasMax'] > 30 ? 'text-red-600 font-medium' : 'text-zinc-500' ?>"> · <?= $a['diasMax'] ?> dias</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="sticky bottom-0 bg-slate-100 font-semibold text-black">
                    <tr>
                        <td class="py-2 px-3">Totales (<?= count($alertas['antiguedad']) ?>)</td>
                        <?php foreach (['cargas', 'r15', 'r30', 'r60m', 'r60', 'total'] as $k): ?><td class="<?= $tdn ?>"><?= $nf($t[$k]) ?></td><?php endforeach; ?>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php // ---- OS sin DO: ordenes armadas por inventario que esperan que trafico las meta a un contenedor. ?>
    <div data-alerta-panel="os" class="<?= $alertaActiva === 'os' ? '' : 'hidden' ?>">
        <p class="text-xs text-zinc-500 mb-2">OS armadas que todavia no tienen DO, la mas antigua primero. En rojo las que llevan mas de 3 dias esperando.</p>
        <?php if ($alertas['osSinDo'] === []): ?>
            <p class="text-sm text-zinc-500 py-4">No hay OS esperando DO.</p>
        <?php else: ?>
        <div class="overflow-x-auto border border-slate-200 rounded-lg">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 px-3">OS</th>
                        <th class="<?= $th ?>">Creada</th>
                        <th class="<?= $thn ?>">Esperando</th>
                        <th class="<?= $th ?>">Aduaneros</th>
                        <th class="<?= $thn ?>">Clientes</th>
                        <th class="<?= $thn ?>">Cargas</th>
                        <th class="<?= $thn ?>">Escaneados</th>
                        <th class="<?= $thn ?>">Kg</th>
                        <th class="<?= $thn ?>">P3</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alertas['osSinDo'] as $o): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-2 px-3 whitespace-nowrap"><a href="/salidas/<?= htmlspecialchars($o['id']) ?>" class="text-amber-600 hover:underline font-medium"><?= htmlspecialchars($o['etiqueta']) ?></a></td>
                            <td class="<?= $th ?> text-zinc-700 whitespace-nowrap"><?= htmlspecialchars($o['creada']->format('d/m/Y H:i')) ?></td>
                            <td class="<?= $tdn ?> <?= $o['dias'] > 3 ? 'text-red-600 font-semibold' : 'text-zinc-700' ?>"><?= $o['horas'] >= 48 ? $o['dias'] . ' dias' : $o['horas'] . ' h' ?></td>
                            <td class="<?= $th ?> text-zinc-700"><?= htmlspecialchars(implode(', ', $o['aduanerosLista'])) ?></td>
                            <td class="<?= $tdn ?> text-zinc-700" title="<?= htmlspecialchars(implode(', ', $o['clientesLista'])) ?>"><?= $o['clientes'] ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $o['cargas'] ?></td>
                            <td class="<?= $tdn ?> <?= $o['escaneados'] >= $o['bultos'] ? 'text-emerald-700 font-medium' : 'text-black' ?>"><?= $o['escaneados'] ?> / <?= $o['bultos'] ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $nf($o['kg'], 1) ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $nf($o['p3'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php // ---- Contenedores incompletos: DO pendientes con bultos por escanear, por fecha de cargue/ETD. ?>
    <div data-alerta-panel="incompletos" class="<?= $alertaActiva === 'incompletos' ? '' : 'hidden' ?>">
        <p class="text-xs text-zinc-500 mb-2">DO pendientes a los que bodega aun no les escanea todos los bultos, el que carga o sale primero arriba. En rojo si la fecha ya paso o es en 2 dias o menos.</p>
        <?php if ($alertas['incompletos'] === []): ?>
            <p class="text-sm text-zinc-500 py-4">Todos los contenedores pendientes estan escaneados completos.</p>
        <?php else: ?>
        <div class="overflow-x-auto border border-slate-200 rounded-lg">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                        <th class="py-2 px-3">DO</th>
                        <th class="<?= $th ?>">Contenedor</th>
                        <th class="<?= $th ?>">Naviera</th>
                        <th class="<?= $th ?>">Cargue</th>
                        <th class="<?= $th ?>">ETD</th>
                        <th class="<?= $thn ?>">Faltan</th>
                        <th class="<?= $thn ?>">Escaneados</th>
                        <th class="<?= $th ?>">Aduaneros</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alertas['incompletos'] as $d):
                        $dias = $d['fechaLimite'] !== null ? (int) $hoy->diff(new DateTimeImmutable($d['fechaLimite']))->format('%r%a') : null;
                        $urgente = $dias !== null && $dias <= 2; ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 <?= $urgente ? 'bg-red-50/60' : '' ?>">
                            <td class="py-2 px-3 whitespace-nowrap"><a href="/salidas/<?= htmlspecialchars($d['id']) ?>" class="text-amber-600 hover:underline font-medium"><?= htmlspecialchars($d['etiqueta']) ?></a></td>
                            <td class="<?= $th ?> font-mono text-zinc-700 whitespace-nowrap"><?= htmlspecialchars($d['contenedor'] ?? '—') ?></td>
                            <td class="<?= $th ?> text-zinc-700"><?= htmlspecialchars($d['naviera'] ?? '—') ?></td>
                            <td class="<?= $th ?> whitespace-nowrap <?= $urgente ? 'text-red-700 font-semibold' : 'text-zinc-700' ?>"><?= htmlspecialchars($d['cargue']?->format('d/m/Y') ?? '—') ?></td>
                            <td class="<?= $th ?> whitespace-nowrap text-zinc-700"><?= htmlspecialchars($d['etd']?->format('d/m/Y') ?? '—') ?></td>
                            <td class="<?= $tdn ?> text-red-700 font-semibold"><?= $nf($d['bultos'] - $d['escaneados']) ?></td>
                            <td class="<?= $tdn ?> text-zinc-700"><?= $d['escaneados'] ?> / <?= $d['bultos'] ?></td>
                            <td class="<?= $th ?> text-zinc-700"><?= htmlspecialchars(implode(', ', $d['aduanerosLista'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
