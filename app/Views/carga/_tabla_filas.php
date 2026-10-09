<?php
/** @var array<int, \Courier\Carga\Domain\Carga> $cargas */
/** @var array<string, string> $nombresClientes */
require_once __DIR__ . '/../partials/view_helpers.php';
if (empty($cargas)):
?>
    <tr><td colspan="6" class="py-4 text-sm text-zinc-500">No se encontraron cargas.</td></tr>
<?php else: foreach ($cargas as $carga): ?>
    <tr class="border-b border-slate-200">
        <td class="py-2 pr-4 font-mono text-black"><?= htmlspecialchars((string) $carga->trackingNumero()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($nombresClientes[$carga->clienteId()] ?? '—') ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($carga->proveedor()) ?></td>
        <td class="py-2 pr-4"><?= badge($carga->estado()->label(), 'amber') ?></td>
        <td class="py-2 pr-4 text-zinc-500"><?= htmlspecialchars($carga->fechaIngreso()->format('d/m/Y')) ?></td>
        <td class="py-2 text-right space-x-2 whitespace-nowrap">
            <?php if ($carga->facturaProveedorRuta()): ?>
                <a href="/<?= htmlspecialchars($carga->facturaProveedorRuta()) ?>" target="_blank" title="Ver factura del proveedor"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500 text-white hover:bg-red-600 transition">
                    <?= icon_small('receipt') ?>
                </a>
            <?php endif; ?>
            <?= icon_button('/cargas/' . $carga->id(), 'eye', 'Ver', 'blue') ?>
            <?= icon_button('/cargas/' . $carga->id() . '/editar', 'pencil', 'Editar', 'amber') ?>
        </td>
    </tr>
<?php endforeach; unset($carga); endif; ?>
