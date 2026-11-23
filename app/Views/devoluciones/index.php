<?php
/** @var array<int, array<string, mixed>> $ordenes */
/** @var string $estado */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Devoluciones al proveedor';
$pageSubtitle = 'Mercancia que el cliente ya no quiere: sale en el camion que la viene a buscar';
$vivoSegundos = 15;
require __DIR__ . '/../partials/header.php';
$h = static fn ($v): string => htmlspecialchars((string) $v);
?>

<?php if (!empty($aviso)): ?>
    <div class="<?= $aviso['tipo'] === 'ok' ? 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30' : 'bg-red-500/10 text-red-600 border-red-500/30' ?> text-sm rounded-lg px-4 py-3 mb-4 border"><?= $h($aviso['texto']) ?></div>
<?php endif; ?>

<div class="flex flex-wrap items-center gap-2 mb-4">
    <?php foreach (['pendiente' => 'Por despachar', 'despachada' => 'Devueltas', 'cancelada' => 'Canceladas', 'todas' => 'Todas'] as $valor => $texto): ?>
        <a href="/devoluciones?estado=<?= $valor ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $estado === $valor ? 'bg-black text-white' : 'bg-white text-zinc-700 shadow-sm hover:bg-slate-50' ?>"><?= $texto ?></a>
    <?php endforeach; ?>
    <?php if (puede_armar_salida()): ?>
        <a href="/devoluciones/nueva" class="ml-auto inline-flex items-center gap-2 bg-orange-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-orange-500"><?= icon_small('plus') ?>Crear orden de devolucion</a>
    <?php endif; ?>
</div>

<div id="devoluciones-lista" data-vivo-region="global" class="bg-white rounded-xl shadow p-4 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                <th class="py-2 pr-3">Orden</th>
                <th class="py-2 pr-3">Estado</th>
                <th class="py-2 pr-3">Proveedor(es)</th>
                <th class="py-2 pr-3">Cliente(s)</th>
                <th class="py-2 pr-3 text-right">Cargas</th>
                <th class="py-2 pr-3 text-right">Bultos</th>
                <th class="py-2 pr-3 text-right">Escaneados</th>
                <th class="py-2 pr-3">Camion</th>
                <?php if (hay_varias_bodegas()): ?><th class="py-2 pr-3">Bodega</th><?php endif; ?>
                <th class="py-2">Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($ordenes === []): ?>
                <tr><td colspan="10" class="py-4 text-zinc-500">No hay ordenes de devolucion.</td></tr>
            <?php endif; ?>
            <?php foreach ($ordenes as $o): $est = \Courier\Devolucion\Domain\EstadoDevolucion::from($o['estado']); ?>
                <tr class="border-b border-slate-100 align-top">
                    <td class="py-2 pr-3">
                        <a href="/devoluciones/<?= $h($o['id']) ?>" class="font-semibold text-blue-700 underline"><?= $h($o['codigo']) ?></a>
                        <p class="text-xs text-zinc-500 max-w-[16rem] truncate" title="<?= $h($o['motivo']) ?>"><?= $h($o['motivo']) ?></p>
                    </td>
                    <td class="py-2 pr-3"><?= badge($est->label(), $est->color()) ?></td>
                    <td class="py-2 pr-3"><?= $h($o['proveedores'] !== '' ? $o['proveedores'] : '—') ?></td>
                    <td class="py-2 pr-3"><?= $h($o['clientes'] !== '' ? $o['clientes'] : '—') ?></td>
                    <td class="py-2 pr-3 text-right"><?= (int) $o['cargas'] ?></td>
                    <td class="py-2 pr-3 text-right"><?= (int) $o['bultos'] ?></td>
                    <td class="py-2 pr-3 text-right <?= $o['estado'] === 'pendiente' && $o['escaneados'] < $o['bultos'] ? 'text-red-600 font-semibold' : '' ?>"><?= (int) $o['escaneados'] ?> / <?= (int) $o['bultos'] ?></td>
                    <td class="py-2 pr-3"><?= $h(trim(($o['empresa'] ?? '') . ($o['placa'] ? ' · ' . $o['placa'] : ''), ' ·') ?: '—') ?></td>
                    <?php if (hay_varias_bodegas()): ?><td class="py-2 pr-3"><?= $h($o['bodegaId'] ? bodega_nombre($o['bodegaId']) : '—') ?></td><?php endif; ?>
                    <td class="py-2 whitespace-nowrap"><?= $h(date('d/m/Y H:i', strtotime($o['confirmada'] ?? $o['creada']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
