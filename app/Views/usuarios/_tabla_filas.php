<?php
use App\Libraries\Csrf;
/** @var array<int, \Courier\Auth\Domain\Usuario> $usuarios */
require_once __DIR__ . '/../partials/view_helpers.php';
$usuarioActualId = $_SESSION['usuario_id'] ?? null;

if (empty($usuarios)):
?>
    <tr><td colspan="5" class="py-4 text-sm text-zinc-500">No se encontraron usuarios.</td></tr>
<?php else: foreach ($usuarios as $usuario): ?>
    <tr class="border-b border-slate-200">
        <td class="py-2 pr-4 font-medium text-black"><?= htmlspecialchars($usuario->nombre()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars((string) $usuario->email()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars(ucfirst($usuario->rol()->value)) ?></td>
        <td class="py-2 pr-4"><?= $usuario->activo() ? badge('Activo', 'emerald') : badge('Inactivo', 'red') ?></td>
        <td class="py-2 text-right space-x-1 whitespace-nowrap">
            <?= icon_button('/usuarios/' . $usuario->id() . '/editar', 'pencil', 'Editar', 'violet') ?>
            <?php if ($usuario->id() !== $usuarioActualId): ?>
                <?php if ($usuario->activo()): ?>
                    <form method="post" action="/usuarios/<?= htmlspecialchars($usuario->id()) ?>/desactivar" class="inline" onsubmit="return confirm('¿Desactivar este usuario?');">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                        <?= icon_submit_button('x', 'Desactivar', 'red') ?>
                    </form>
                <?php else: ?>
                    <form method="post" action="/usuarios/<?= htmlspecialchars($usuario->id()) ?>/activar" class="inline">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                        <?= icon_submit_button('check', 'Activar', 'green') ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; unset($usuario); endif; ?>
