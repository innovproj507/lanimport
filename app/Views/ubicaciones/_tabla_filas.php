<?php
use App\Libraries\Csrf;
/** @var array<int, \Courier\Lpn\Domain\Ubicacion> $ubicaciones */
require_once __DIR__ . '/../partials/view_helpers.php';
if (empty($ubicaciones)):
?>
    <tr><td colspan="6" class="py-4 text-sm text-zinc-500">No se encontraron ubicaciones.</td></tr>
<?php else: foreach ($ubicaciones as $ubicacion): ?>
    <tr class="border-b border-slate-200">
        <td class="py-2 pr-4 font-mono text-black"><?= htmlspecialchars($ubicacion->codigo()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($ubicacion->zona() ?? '—') ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($ubicacion->pasillo() ?? '—') ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars($ubicacion->nivel() ?? '—') ?></td>
        <td class="py-2 pr-4"><?= $ubicacion->activo() ? badge('Activa', 'emerald') : badge('Inactiva', 'red') ?></td>
        <td class="py-2 text-right space-x-1 whitespace-nowrap">
            <?= icon_button('/ubicaciones/' . $ubicacion->id() . '/editar', 'pencil', 'Editar', 'amber') ?>
            <?php if ($ubicacion->activo()): ?>
                <form method="post" action="/ubicaciones/<?= htmlspecialchars($ubicacion->id()) ?>/desactivar" class="inline" onsubmit="return confirm('¿Desactivar esta ubicacion?');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <?= icon_submit_button('x', 'Desactivar', 'red') ?>
                </form>
            <?php else: ?>
                <form method="post" action="/ubicaciones/<?= htmlspecialchars($ubicacion->id()) ?>/activar" class="inline">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <?= icon_submit_button('check', 'Activar', 'green') ?>
                </form>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; unset($ubicacion); endif; ?>
