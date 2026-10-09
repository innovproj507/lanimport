<?php
use App\Libraries\Csrf;
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Nuevo Usuario';
$pageSubtitle = 'Registrar un nuevo usuario del sistema';
$backHref = '/usuarios';
require __DIR__ . '/../partials/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/usuarios" class="bg-white rounded-xl shadow p-6 max-w-xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('users', 'Datos del usuario', 'violet') ?>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre</label>
        <input type="text" name="nombre" required
               class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Correo electronico</label>
        <input type="email" name="email" required
               class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Contrasena</label>
        <input type="password" name="password" required minlength="8"
               class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Rol</label>
        <select name="rol" required class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
            <option value="">Seleccione...</option>
            <?php foreach (\Courier\Auth\Domain\Rol::all() as $rolOpcion): ?>
                <option value="<?= htmlspecialchars($rolOpcion->value) ?>"><?= htmlspecialchars(ucfirst($rolOpcion->value)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
            <?= icon_small('check') ?><span>Registrar usuario</span>
        </button>
        <a href="/usuarios" class="inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-6 py-2.5 font-medium hover:bg-slate-300 transition">
            <span>Cancelar</span>
        </a>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
