<?php
use App\Libraries\Csrf;
/** @var array<int, \Courier\Cliente\Domain\Cliente> $clientes */
/** @var array<int, \Courier\Cliente\Domain\Cliente> $pendientes */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalPages */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Clientes';
$pageSubtitle = 'Clientes registrados en el sistema';
$headerActions = '<button type="button" id="btn-nuevo-cliente" class="inline-flex items-center gap-2 bg-emerald-500 text-black rounded-lg px-4 py-2 text-sm font-medium hover:bg-emerald-400 transition">'
    . icon_small('users') . '<span>+ Nuevo Cliente</span></button>';
require __DIR__ . '/../partials/header.php';
?>

<?php if (!empty($pendientes)): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="text-sm font-semibold text-amber-600 uppercase tracking-wide mb-4 flex items-center gap-2"><?= icon_small('clock', 'w-4 h-4') ?> Nuevos registros (<?= count($pendientes) ?>)</h2>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                <th class="py-2 pr-4">Nombre</th>
                <th class="py-2 pr-4">Email</th>
                <th class="py-2 pr-4">Empresa</th>
                <th class="py-2 pr-4">Telefono</th>
                <th class="py-2">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendientes as $solicitud): ?>
                <tr class="border-b border-slate-200">
                    <td class="py-2 pr-4 font-medium text-black"><?= htmlspecialchars($solicitud->nombreCompleto()) ?></td>
                    <td class="py-2 pr-4 text-zinc-700"><?= htmlspecialchars((string) $solicitud->email()) ?></td>
                    <td class="py-2 pr-4 text-zinc-500"><?= htmlspecialchars($solicitud->empresa() ?? '—') ?></td>
                    <td class="py-2 pr-4 text-zinc-500"><?= htmlspecialchars($solicitud->telefono() ?? '—') ?></td>
                    <td class="py-2 space-x-2 whitespace-nowrap">
                        <form method="post" action="/clientes/<?= htmlspecialchars($solicitud->id()) ?>/aprobar" class="inline">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <?= icon_submit_button('check', 'Aprobar', 'green') ?>
                        </form>
                        <form method="post" action="/clientes/<?= htmlspecialchars($solicitud->id()) ?>/rechazar" class="inline" onsubmit="return confirm('¿Rechazar esta solicitud?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                            <?= icon_submit_button('x', 'Rechazar', 'red') ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6">
    <div class="mb-4 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2">
            <?= icon_small('search', 'w-4 h-4 text-zinc-500') ?>
            <input type="text" id="buscar-clientes" placeholder="Buscar por nombre, email, empresa o codigo..."
                   class="w-full max-w-sm rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
        </div>
        <div class="flex items-center gap-2 text-sm text-zinc-600">
            <label for="clientes-per-page">Mostrar</label>
            <select id="clientes-per-page" class="rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
                <?php foreach ([5, 10, 25] as $opcion): ?>
                    <option value="<?= $opcion ?>" <?= $perPage === $opcion ? 'selected' : '' ?>><?= $opcion ?></option>
                <?php endforeach; ?>
            </select>
            <span>filas</span>
        </div>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-zinc-500 uppercase border-b border-slate-200">
                <th class="py-2 pr-4">Codigo</th>
                <th class="py-2 pr-4">Nombre</th>
                <th class="py-2 pr-4">Email</th>
                <th class="py-2 pr-4">Telefono</th>
                <th class="py-2 pr-4">Empresa</th>
                <th class="py-2 pr-4">Estado</th>
                <th class="py-2 text-right">Acciones</th>
            </tr>
        </thead>
        <tbody id="clientes-tbody">
            <?php require __DIR__ . '/_tabla_filas.php'; ?>
        </tbody>
    </table>
    <div id="clientes-paginacion">
        <?php require __DIR__ . '/_paginacion.php'; ?>
    </div>
</div>

<div id="modal-nuevo-cliente" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
    <div class="bg-white border border-slate-200 rounded-xl shadow-xl p-6 max-w-xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <span class="w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0"><?= icon_small('users', 'w-5 h-5') ?></span>
                <h2 class="text-lg font-semibold text-black">Nuevo Cliente</h2>
            </div>
            <button type="button" id="btn-cerrar-modal-cliente" class="text-zinc-500 hover:text-black">&times;</button>
        </div>
        <form method="post" action="/clientes" class="space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <?php require __DIR__ . '/_form_campos.php'; ?>
            <button type="submit" class="inline-flex items-center gap-2 bg-emerald-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-emerald-400 transition">
                <?= icon_small('check') ?><span>Registrar cliente</span>
            </button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
