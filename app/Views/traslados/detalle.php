<?php
use App\Libraries\Csrf;
require_once __DIR__ . '/../partials/view_helpers.php';
/** @var \Courier\Traslado\Domain\Traslado $traslado */
/** @var array<int, array<string, mixed>> $lineas */
/** @var string|null $fija bodega fija del usuario */
$estadoT = $traslado->estado()->value;
$pageTitle = 'Traslado ' . $traslado->etiqueta();
$pageSubtitle = bodega_nombre($traslado->origenBodegaId()) . ' → ' . bodega_nombre($traslado->destinoBodegaId());
$backHref = '/traslados';
$vivoSegundos = 8;
require __DIR__ . '/../partials/header.php';
$h = static fn ($v): string => htmlspecialchars((string) $v);
$p = $traslado->progreso();
$colores = ['pendiente' => 'amber', 'en_camino' => 'blue', 'recibido' => 'emerald', 'cancelado' => 'slate'];
// Quien escanea ahora: la bodega origen mientras se carga; la destino cuando va en camino.
$leTocaA = $estadoT === 'pendiente' ? $traslado->origenBodegaId() : ($estadoT === 'en_camino' ? $traslado->destinoBodegaId() : null);
$puedeEscanear = $leTocaA !== null && ($fija === null || $fija === $leTocaA);
// Agrupado por carga.
$grupos = [];
foreach ($lineas as $l) {
    $grupos[$l['tracking']] ??= ['cargaId' => $l['cargaId'], 'cliente' => $l['cliente'], 'lineas' => []];
    $grupos[$l['tracking']]['lineas'][] = $l;
}
?>

<?php if (!empty($aviso)): ?>
    <div class="<?= $aviso['tipo'] === 'ok' ? 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30' : 'bg-red-500/10 text-red-600 border-red-500/30' ?> text-sm rounded-lg px-4 py-3 mb-4 border max-w-5xl"><?= $h($aviso['texto']) ?></div>
<?php endif; ?>

<div id="traslado-detalle" data-vivo-region="global" class="max-w-5xl space-y-4">
    <div class="bg-white rounded-xl shadow p-5 flex flex-wrap items-center gap-x-8 gap-y-3">
        <div><p class="text-xs text-zinc-500 uppercase">Estado</p><?= badge($traslado->estado()->label(), $colores[$estadoT] ?? 'slate') ?></div>
        <div><p class="text-xs text-zinc-500 uppercase">De</p><p class="font-semibold text-black"><?= $h(bodega_nombre($traslado->origenBodegaId())) ?></p></div>
        <div><p class="text-xs text-zinc-500 uppercase">A</p><p class="font-semibold text-black"><?= $h(bodega_nombre($traslado->destinoBodegaId())) ?></p></div>
        <div><p class="text-xs text-zinc-500 uppercase">Cargados</p><p class="font-semibold text-black"><?= $p['salida'] ?> / <?= $p['total'] ?></p></div>
        <div><p class="text-xs text-zinc-500 uppercase">Llegaron</p><p class="font-semibold <?= $estadoT === 'en_camino' && $p['llegada'] < $p['total'] ? 'text-red-600' : 'text-black' ?>"><?= $p['llegada'] ?> / <?= $p['total'] ?></p></div>
        <div><p class="text-xs text-zinc-500 uppercase">Creado</p><p class="text-black"><?= $h($traslado->createdAt()->format('d/m/Y H:i')) ?></p></div>
        <?php if ($traslado->despachadoAt() !== null): ?>
            <div><p class="text-xs text-zinc-500 uppercase">Salio</p><p class="text-black"><?= $h($traslado->despachadoAt()->format('d/m/Y H:i')) ?></p></div>
        <?php endif; ?>
        <?php if ($traslado->nota() !== null): ?>
            <div class="basis-full text-sm text-zinc-700"><strong>Nota:</strong> <?= $h($traslado->nota()) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($puedeEscanear): ?>
        <form method="post" action="/traslados/<?= $h($traslado->id()) ?>/escanear" class="bg-white rounded-xl shadow p-5 flex flex-wrap items-end gap-3">
            <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
            <div class="flex-1 min-w-[16rem]">
                <label class="block text-sm font-medium text-zinc-700 mb-1" for="codigo">
                    <?= $estadoT === 'pendiente' ? 'Escanee cada bulto al cargarlo en el camion' : 'Escanee cada bulto al llegar a ' . $h(bodega_nombre($traslado->destinoBodegaId())) ?>
                </label>
                <input type="text" name="codigo" id="codigo" autofocus autocomplete="off" placeholder="LPN-XXXXXX-1"
                       class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-lg font-mono focus:ring-2 focus:ring-amber-400 focus:outline-none">
            </div>
            <button type="submit" class="bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400">Escanear</button>
        </form>
    <?php endif; ?>

    <?php if ($estadoT === 'pendiente'): ?>
        <div class="flex flex-wrap gap-3">
            <?php if ($fija === null || $fija === $traslado->origenBodegaId()): ?>
                <form method="post" action="/traslados/<?= $h($traslado->id()) ?>/despachar"
                      onsubmit="return confirm('¿Despachar el traslado? Van los <?= $p['salida'] ?> bultos escaneados<?= $p['salida'] < $p['total'] ? '; los ' . ($p['total'] - $p['salida']) . ' sin escanear se quedan en ' . $h(bodega_nombre($traslado->origenBodegaId())) : '' ?>.');">
                    <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
                    <button type="submit" <?= $p['salida'] === 0 ? 'disabled' : '' ?> class="bg-blue-600 text-white rounded-lg px-5 py-2.5 font-medium hover:bg-blue-500 disabled:opacity-40">Despachar (salio el camion)</button>
                </form>
            <?php endif; ?>
            <?php if (puede_armar_salida()): ?>
                <form method="post" action="/traslados/<?= $h($traslado->id()) ?>/cancelar" onsubmit="return confirm('¿Cancelar el traslado? Los bultos quedan disponibles en la bodega origen.');">
                    <input type="hidden" name="_csrf" value="<?= $h(Csrf::token()) ?>">
                    <button type="submit" class="bg-slate-200 text-zinc-800 rounded-lg px-5 py-2.5 font-medium hover:bg-slate-300">Cancelar traslado</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow p-5 overflow-x-auto">
        <?= card_header('box', 'Bultos (' . $p['total'] . ')', 'indigo') ?>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-3">Etiqueta</th>
                    <th class="py-2 pr-3">Cargado</th>
                    <th class="py-2">Llego</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grupos as $tracking => $g): ?>
                    <tr class="bg-slate-50">
                        <td colspan="3" class="py-1.5 px-2 font-semibold text-black">
                            <?php if ($g['cargaId'] !== null): ?><a href="/cargas/<?= $h($g['cargaId']) ?>" class="hover:underline"><?= $h($tracking) ?></a><?php else: ?><?= $h($tracking) ?><?php endif; ?>
                            <span class="font-normal text-zinc-600">· <?= $h($g['cliente']) ?> · <?= count($g['lineas']) ?> bulto(s)</span>
                        </td>
                    </tr>
                    <?php foreach ($g['lineas'] as $l): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 pr-3 font-mono"><?= $h($l['codigo']) ?></td>
                            <td class="py-1.5 pr-3"><?= $l['salida'] !== null ? '<span class="text-emerald-700">Si · ' . $h(date('d/m H:i', strtotime((string) $l['salida']))) . '</span>' : '<span class="text-zinc-400">No</span>' ?></td>
                            <td class="py-1.5"><?= $l['llegada'] !== null ? '<span class="text-emerald-700">Si · ' . $h(date('d/m H:i', strtotime((string) $l['llegada']))) . '</span>' : ($estadoT === 'en_camino' ? '<span class="text-red-600">Falta</span>' : '<span class="text-zinc-400">—</span>') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
