<?php
use App\Libraries\Csrf;
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
require_once __DIR__ . '/../partials/view_helpers.php';
if (empty($clientes)):
?>
    <tr><td colspan="7" class="py-4 text-sm text-zinc-500">No se encontraron clientes.</td></tr>
<?php else: foreach ($clientes as $cliente): ?>
    <tr class="border-b border-slate-200">
        <td class="py-2 pr-4 text-zinc-500 font-mono text-xs"><?= htmlspecialchars($cliente->codigo() ?? '—') ?></td>
        <td class="py-2 pr-4 font-medium text-black"><?= htmlspecialchars($cliente->nombreCompleto()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars((string) $cliente->email()) ?></td>
        <td class="py-2 pr-4 text-zinc-500"><?= htmlspecialchars($cliente->telefono() ?? '—') ?></td>
        <td class="py-2 pr-4 text-zinc-500"><?= htmlspecialchars($cliente->empresa() ?? '—') ?></td>
        <td class="py-2 pr-4"><?= badge('Activo', 'emerald') ?></td>
        <td class="py-2 text-right space-x-1 whitespace-nowrap">
            <?= icon_button('/clientes/' . $cliente->id(), 'eye', 'Ver', 'blue') ?>
            <?= icon_button('/clientes/' . $cliente->id() . '/editar', 'pencil', 'Editar', 'violet') ?>
            <form method="post" action="/clientes/<?= htmlspecialchars($cliente->id()) ?>/eliminar" class="inline" onsubmit="return confirm('¿Eliminar este cliente?');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <?= icon_submit_button('trash', 'Eliminar', 'red') ?>
            </form>
        </td>
    </tr>
<?php endforeach; unset($cliente); endif; ?>
