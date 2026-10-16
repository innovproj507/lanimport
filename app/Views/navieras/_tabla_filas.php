<?php
/** @var array<int, \Courier\Naviera\Domain\Naviera> $navieras */
require_once __DIR__ . '/../partials/view_helpers.php';
if (empty($navieras)):
?>
    <tr><td colspan="3" class="py-4 text-sm text-zinc-500">No se encontraron navieras.</td></tr>
<?php else: foreach ($navieras as $naviera): ?>
    <tr class="border-b border-slate-200">
        <td class="py-2 pr-4 font-medium text-black"><?= htmlspecialchars($naviera->nombre()) ?></td>
        <td class="py-2 pr-4"><?= $naviera->activo() ? badge('Activa', 'emerald') : badge('Inactiva', 'slate') ?></td>
        <td class="py-2 text-right space-x-1 whitespace-nowrap">
            <?= icon_button('/navieras/' . $naviera->id() . '/editar', 'pencil', 'Editar', 'amber') ?>
            <?php $base = '/navieras/' . $naviera->id(); $activo = $naviera->activo(); $nombre = 'la naviera ' . $naviera->nombre(); require __DIR__ . '/../partials/_acciones_estado.php'; ?>
        </td>
    </tr>
<?php endforeach; unset($naviera); endif; ?>
