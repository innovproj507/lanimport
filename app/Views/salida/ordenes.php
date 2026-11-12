<?php
/**
 * Orden de Salida: las OS que armo inventario y todavia no tienen DO. Bodega ya las escanea;
 * trafico abre cada una para generar el DO y ordenar el contenedor, y entonces pasa a Salidas.
 *
 * @var array<int, \Courier\Salida\Domain\SalidaOrden> $ordenes
 * @var array<string, array<int, string>> $clientesPorOrden
 * @var array<string, array<int, string>> $aduanerosPorOrden
 */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Orden de Salida';
$pageSubtitle = 'Ordenes de salida armadas por inventario';
$headerActions = puede_armar_salida() ? header_button('/salidas', 'plus', 'Crear OS', 'amber') : '';
require __DIR__ . '/../partials/header.php';
$armaOs = puede_armar_salida();
?>

<div class="bg-sky-50 border border-sky-200 text-sky-900 text-sm rounded-xl px-4 py-3 mb-4">
    Aqui se arman las ordenes de salida: <strong>Crear OS</strong>, agregar o quitar cargas, devolverlas a bodega o eliminar la OS. Bodega ya puede escanearlas.
</div>

<div class="bg-white rounded-xl shadow-sm p-6" id="ordenes-salida" data-vivo-region="global">
    <?php if ($ordenes === []): ?>
        <p class="text-sm text-zinc-500">No hay ordenes de salida.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                    <th class="py-2 pr-4">OS</th>
                    <th class="py-2 pr-4">Aduanero</th>
                    <th class="py-2 pr-4">Clientes</th>
                    <th class="py-2 pr-4">Escaneo de bodega</th>
                    <th class="py-2 pr-4">Creada</th>
                    <th class="py-2 text-right"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordenes as $orden): $p = $orden->progreso(); $completa = $p['total'] > 0 && $p['escaneadas'] >= $p['total']; ?>
                    <tr class="border-b border-slate-200">
                        <td class="py-2 pr-4 font-medium whitespace-nowrap">
                            <a href="/salidas/<?= htmlspecialchars($orden->id()) ?>" class="text-amber-600 hover:underline"><?= htmlspecialchars($orden->etiquetaDo()) ?></a>
                        </td>
                        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars(implode(', ', $aduanerosPorOrden[$orden->id()] ?? ['—'])) ?></td>
                        <?php $clientes = $clientesPorOrden[$orden->id()] ?? []; ?>
                        <td class="py-2 pr-4 text-zinc-700" title="<?= htmlspecialchars(implode(', ', $clientes)) ?>">
                            <?= htmlspecialchars(implode(', ', array_slice($clientes, 0, 2)) . (count($clientes) > 2 ? ' +' . (count($clientes) - 2) . ' mas' : '')) ?: '—' ?>
                        </td>
                        <td class="py-2 pr-4 whitespace-nowrap">
                            <span class="<?= $completa ? 'text-emerald-700 font-medium' : 'text-black' ?>"><?= $p['escaneadas'] ?> de <?= $p['total'] ?></span>
                            <div class="w-28 bg-slate-200 rounded-full h-1.5 mt-1"><div class="<?= $completa ? 'bg-emerald-500' : 'bg-amber-500' ?> h-1.5 rounded-full" style="width: <?= (int) round($p['escaneadas'] * 100 / max(1, $p['total'])) ?>%"></div></div>
                        </td>
                        <td class="py-2 pr-4 text-zinc-700 whitespace-nowrap"><?= htmlspecialchars($orden->createdAt()->format('Y-m-d H:i')) ?></td>
                        <td class="py-2 text-right whitespace-nowrap">
                            <a href="/salidas/<?= htmlspecialchars($orden->id()) ?>"
                               class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium transition bg-amber-500 text-black hover:bg-amber-400">
                                <?= icon_small($armaOs ? 'pencil' : 'eye', 'w-3.5 h-3.5') ?><?= $armaOs ? 'Modificar' : 'Ver' ?>
                            </a>
                            <?php if ($armaOs): ?>
                                <?php // Eliminar la OS: sus bultos vuelven a quedar disponibles en bodega. ?>
                                <form method="post" action="/salidas/<?= htmlspecialchars($orden->id()) ?>/cancelar" class="inline"
                                      onsubmit="return confirm('¿Eliminar la <?= htmlspecialchars($orden->etiquetaDo()) ?>? Sus <?= $p['total'] ?> bultos vuelven a quedar disponibles en bodega.');">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Libraries\Csrf::token()) ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium transition bg-red-500/10 text-red-600 hover:bg-red-500/20">
                                        <?= icon_small('trash', 'w-3.5 h-3.5') ?>Eliminar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
