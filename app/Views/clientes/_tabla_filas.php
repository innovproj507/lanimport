<?php
use App\Libraries\Csrf;
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
require_once __DIR__ . '/../partials/view_helpers.php';
/** @var array<string, string> $nombresAduaneros identificador => nombre */
$nombresAduaneros = $nombresAduaneros ?? [];
if (empty($clientes)):
?>
    <tr><td colspan="8" class="py-4 text-sm text-zinc-500">No se encontraron clientes.</td></tr>
<?php else: foreach ($clientes as $cliente): ?>
    <tr class="border-b border-slate-200">
        <td class="py-2 pr-4 text-zinc-500 font-mono text-xs"><?= htmlspecialchars($cliente->codigo() ?? '—') ?></td>
        <td class="py-2 pr-4 font-medium text-black"><?= htmlspecialchars($cliente->nombreCompleto()) ?></td>
        <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars((string) $cliente->email()) ?></td>
        <td class="py-2 pr-4 text-zinc-500"><?= htmlspecialchars($cliente->telefono() ?? '—') ?></td>
        <td class="py-2 pr-4 text-zinc-500"><?= htmlspecialchars($cliente->empresa() ?? '—') ?></td>
        <td class="py-2 pr-4 text-zinc-500"><?= htmlspecialchars($nombresAduaneros[$cliente->aduaneroIdentificador() ?? ''] ?? $cliente->aduaneroIdentificador() ?? '—') ?></td>
        <td class="py-2 pr-4"><?= $cliente->activo() ? badge('Activo', 'emerald') : badge('Inactivo', 'slate') ?></td>
        <td class="py-2 text-right space-x-1 whitespace-nowrap">
            <?= icon_button('/clientes/' . $cliente->id(), 'eye', 'Ver', 'blue') ?>
            <?= icon_button('/clientes/' . $cliente->id() . '/editar', 'pencil', 'Editar', 'violet') ?>
            <?php $base = '/clientes/' . $cliente->id(); $activo = $cliente->activo(); $nombre = 'al cliente ' . $cliente->nombreCompleto(); require __DIR__ . '/../partials/_acciones_estado.php'; ?>
        </td>
    </tr>
<?php endforeach; unset($cliente); endif; ?>
