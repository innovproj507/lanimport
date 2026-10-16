<?php
use App\Libraries\Csrf;
/** @var \Courier\Naviera\Domain\Naviera $naviera */
/** @var string|null $error */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Editar Naviera';
$pageSubtitle = $naviera->nombre();
$backHref = '/navieras';
require __DIR__ . '/../partials/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/navieras/<?= htmlspecialchars($naviera->id()) ?>/editar" class="bg-white rounded-xl shadow p-6 max-w-xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('globe', 'Datos de la naviera', 'sky') ?>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre</label>
        <input type="text" name="nombre" required maxlength="120" value="<?= htmlspecialchars($naviera->nombre()) ?>"
               class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 uppercase focus:ring-2 focus:ring-amber-400 focus:outline-none">
    </div>
    <label class="flex items-start gap-2">
        <input type="checkbox" name="activo" value="1" <?= $naviera->activo() ? 'checked' : '' ?>
               class="mt-1 rounded border-zinc-300 text-amber-500 focus:ring-amber-400">
        <span class="text-sm text-zinc-700">
            Activa
            <span class="block text-xs text-zinc-500">Las navieras inactivas no se ofrecen al registrar salidas nuevas, pero se conservan en las salidas anteriores.</span>
        </span>
    </label>
    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
            <?= icon_small('check') ?><span>Guardar cambios</span>
        </button>
        <a href="/navieras" class="inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-6 py-2.5 font-medium hover:bg-slate-300 transition">
            <span>Cancelar</span>
        </a>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
