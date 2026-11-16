<?php
/** @var array<int, \Courier\Traslado\Domain\Traslado> $traslados */
$pageTitle = 'Traslados';
$pageSubtitle = 'Bultos que pasan de una bodega a otra';
$vivoSegundos = 15;
require __DIR__ . '/../partials/header.php';
$h = static fn ($v): string => htmlspecialchars((string) $v);
$colores = ['pendiente' => 'amber', 'en_camino' => 'blue', 'recibido' => 'emerald', 'cancelado' => 'slate'];
?>

<?php if (!empty($aviso)): ?>
    <div class="<?= $aviso['tipo'] === 'ok' ? 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30' : 'bg-red-500/10 text-red-600 border-red-500/30' ?> text-sm rounded-lg px-4 py-3 mb-4 border max-w-5xl"><?= $h($aviso['texto']) ?></div>
<?php endif; ?>

<div class="flex flex-wrap items-center gap-2 mb-4">
    <?php foreach (['abiertos' => 'Abiertos', 'pendiente' => 'Por despachar', 'en_camino' => 'En camino', 'recibido' => 'Recibidos', 'todos' => 'Todos'] as $valor => $texto): ?>
        <a href="/traslados?estado=<?= $valor ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $estado === $valor ? 'bg-black text-white' : 'bg-white text-zinc-700 shadow-sm hover:bg-slate-50' ?>"><?= $texto ?></a>
    <?php endforeach; ?>
    <?php if (puede_armar_salida()): ?>
        <a href="/traslados/nuevo" class="ml-auto inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-400"><?= icon_small('plus') ?>Nuevo traslado</a>
    <?php endif; ?>
</div>

<div id="traslados-lista" data-vivo-region="global" class="bg-white rounded-xl shadow p-4 max-w-5xl overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                <th class="py-2 pr-3">Traslado</th>
                <th class="py-2 pr-3">De</th>
                <th class="py-2 pr-3">A</th>
                <th class="py-2 pr-3">Estado</th>
                <th class="py-2 pr-3 text-right">Bultos</th>
                <th class="py-2 pr-3 text-right">Cargados</th>
                <th class="py-2 pr-3 text-right">Llegaron</th>
                <th class="py-2">Creado</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($traslados === []): ?>
                <tr><td colspan="8" class="py-4 text-zinc-500">No hay traslados.</td></tr>
            <?php endif; ?>
            <?php foreach ($traslados as $t): $p = $t->progreso(); ?>
                <tr class="border-b border-slate-100">
                    <td class="py-2 pr-3"><a href="/traslados/<?= $h($t->id()) ?>" class="font-semibold text-blue-700 underline"><?= $h($t->etiqueta()) ?></a></td>
                    <td class="py-2 pr-3"><?= $h(bodega_nombre($t->origenBodegaId())) ?></td>
                    <td class="py-2 pr-3"><?= $h(bodega_nombre($t->destinoBodegaId())) ?></td>
                    <td class="py-2 pr-3"><?= badge($t->estado()->label(), $colores[$t->estado()->value] ?? 'slate') ?></td>
                    <td class="py-2 pr-3 text-right"><?= $p['total'] ?></td>
                    <td class="py-2 pr-3 text-right"><?= $p['salida'] ?></td>
                    <td class="py-2 pr-3 text-right <?= $t->estado()->value === 'en_camino' && $p['llegada'] < $p['total'] ? 'text-red-600 font-semibold' : '' ?>"><?= $p['llegada'] ?></td>
                    <td class="py-2"><?= $h($t->createdAt()->format('d/m/Y H:i')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
