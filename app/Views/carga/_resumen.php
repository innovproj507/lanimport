<?php
/** Totales de todas las cargas que cumplen los filtros (no solo las de la pagina). */
/** @var int $total */
/** @var array{bultos: float, peso: float, pies3: float, valor: float} $resumen */
$n = static fn (float $v, int $d = 2): string => number_format($v, $d, '.', ',');
?>
<div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    <?php foreach ([
        ['Cargas', number_format($total)],
        ['Bultos', $n($resumen['bultos'], 0)],
        ['Peso total', $n($resumen['peso']) . ' kg'],
        ['Cubicaje', $n($resumen['pies3']) . ' p3'],
        ['Valor', '$' . $n($resumen['valor'])],
    ] as [$etiqueta, $valor]): ?>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2">
            <p class="text-xs text-zinc-500 uppercase"><?= $etiqueta ?></p>
            <p class="text-lg font-semibold text-black"><?= htmlspecialchars($valor) ?></p>
        </div>
    <?php endforeach; ?>
</div>
