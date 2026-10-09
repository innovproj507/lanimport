<?php
/** @var array<int, \Courier\Proveedor\Domain\Proveedor> $proveedores */
require_once __DIR__ . '/../partials/view_helpers.php';
if (empty($proveedores)):
?>
    <tr><td colspan="3" class="py-4 text-sm text-zinc-500">No se encontraron proveedores.</td></tr>
<?php else: foreach ($proveedores as $proveedor): ?>
    <tr class="border-b border-slate-200">
        <td class="py-2 pr-4 font-mono text-black"><?= htmlspecialchars($proveedor->identificador()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($proveedor->nombre()) ?></td>
        <td class="py-2 text-right"><?= icon_button('/proveedores/' . $proveedor->id() . '/editar', 'pencil', 'Editar', 'amber') ?></td>
    </tr>
<?php endforeach; unset($proveedor); endif; ?>
