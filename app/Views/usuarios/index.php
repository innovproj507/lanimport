<?php
/** @var array<int, \Courier\Auth\Domain\Usuario> $usuarios */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalPages */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Usuarios';
$pageSubtitle = 'Usuarios del sistema';
$backHref = '/configuracion';
$headerActions = header_button('/usuarios/nuevo', 'plus', 'Nuevo Usuario', 'amber');
require __DIR__ . '/../partials/header.php';
?>

<div class="bg-white rounded-xl shadow-sm p-6">
    <div class="mb-4 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2">
            <?= icon_small('search', 'w-4 h-4 text-zinc-500') ?>
            <input type="text" id="buscar-usuarios" placeholder="Buscar por nombre o correo..."
                   class="w-full max-w-sm rounded-lg border border-zinc-300 bg-white text-black placeholder-zinc-500 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
        </div>
        <div class="flex items-center gap-2 text-sm text-zinc-600">
            <label for="usuarios-per-page">Mostrar</label>
            <select id="usuarios-per-page" class="rounded-lg border border-zinc-300 bg-white text-black px-2 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 focus:outline-none">
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
                <th class="py-2 pr-4">Nombre</th>
                <th class="py-2 pr-4">Correo</th>
                <th class="py-2 pr-4">Rol</th>
                <th class="py-2 pr-4">Estado</th>
                <th class="py-2 text-right">Acciones</th>
            </tr>
        </thead>
        <tbody id="usuarios-tbody">
            <?php require __DIR__ . '/_tabla_filas.php'; ?>
        </tbody>
    </table>
    <div id="usuarios-paginacion">
        <?php require __DIR__ . '/_paginacion.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
