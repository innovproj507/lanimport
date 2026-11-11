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
$pageSubtitle = 'OS armadas por inventario que esperan el DO de trafico';
$headerActions = puede_armar_salida() ? header_button('/salidas', 'plus', 'Crear OS', 'amber') : '';
require __DIR__ . '/../partials/header.php';
$ordenaContenedor = puede_ordenar_contenedor();
?>

<div class="bg-sky-50 border border-sky-200 text-sky-900 text-sm rounded-xl px-4 py-3 mb-4">
    <?= $ordenaContenedor
        ? 'Pulse <strong>Generar DO</strong> en una OS (o <strong>Crear DO</strong> en Contenedor para juntar varias OS en un contenedor): se llena el contenedor y la OS pasa a <strong>Contenedor</strong>.'
        : 'Bodega ya puede escanear estas OS. Cuando trafico genere el DO pasan a <strong>Contenedor</strong>, y ahi se confirma la salida cuando el contenedor sale.' ?>
</div>

<div class="bg-white rounded-xl shadow-sm p-6" id="ordenes-salida" data-vivo-region="global">
    <?php if ($ordenes === []): ?>
        <p class="text-sm text-zinc-500">No hay ordenes de salida esperando DO.</p>
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
                            <span class="block mt-1"><?= badge('Sin DO', 'amber') ?></span>
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
                            <a href="<?= $ordenaContenedor ? '/salidas/crear-do?os=' . htmlspecialchars($orden->id()) : '/salidas/' . htmlspecialchars($orden->id()) ?>"
                               class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium transition <?= $ordenaContenedor ? 'bg-sky-600 text-white hover:bg-sky-500' : 'bg-slate-100 text-zinc-700 hover:bg-slate-200' ?>">
                                <?= icon_small($ordenaContenedor ? 'truck' : 'eye', 'w-3.5 h-3.5') ?><?= $ordenaContenedor ? 'Generar DO' : 'Ver' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
