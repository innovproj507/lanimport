<?php
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalPages */
$inicio = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$fin = min($total, $page * $perPage);

if (!function_exists('usuariosRangoPaginas')) {
    /** @return array<int, int|string> */
    function usuariosRangoPaginas(int $page, int $totalPages, int $delta = 1): array
    {
        $rango = [];

        for ($i = max(1, $page - $delta); $i <= min($totalPages, $page + $delta); $i++) {
            $rango[] = $i;
        }

        if ($rango[0] > 1) {
            if ($rango[0] > 2) {
                array_unshift($rango, '...');
            }
            array_unshift($rango, 1);
        }

        $ultimo = end($rango);

        if ($ultimo < $totalPages) {
            if ($ultimo < $totalPages - 1) {
                $rango[] = '...';
            }
            $rango[] = $totalPages;
        }

        return $rango;
    }
}
?>
<div class="flex items-center justify-between mt-4 text-sm text-zinc-600 flex-wrap gap-3">
    <span>
        <?php if ($total === 0 || $inicio > $fin): ?>
            Sin resultados
        <?php else: ?>
            Mostrando <?= $inicio ?>-<?= $fin ?> de <?= $total ?>
        <?php endif; ?>
    </span>
    <?php if ($totalPages > 1): ?>
        <div class="flex items-center gap-1 flex-wrap">
            <button type="button" class="usuarios-pagina-btn px-3 py-1.5 rounded-lg border border-zinc-300 text-zinc-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white"
                    data-page="<?= max(1, $page - 1) ?>" <?= $page <= 1 ? 'disabled' : '' ?>>&lsaquo;</button>
            <?php foreach (usuariosRangoPaginas($page, $totalPages) as $p): ?>
                <?php if ($p === '...'): ?>
                    <span class="px-2 text-zinc-400">&hellip;</span>
                <?php else: ?>
                    <button type="button"
                            class="usuarios-pagina-btn px-3 py-1.5 rounded-lg border <?= $p === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-zinc-300 text-zinc-700 hover:bg-slate-100' ?>"
                            data-page="<?= $p ?>"><?= $p ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
            <button type="button" class="usuarios-pagina-btn px-3 py-1.5 rounded-lg border border-zinc-300 text-zinc-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white"
                    data-page="<?= min($totalPages, $page + 1) ?>" <?= $page >= $totalPages ? 'disabled' : '' ?>>&rsaquo;</button>
        </div>
    <?php endif; ?>
</div>
