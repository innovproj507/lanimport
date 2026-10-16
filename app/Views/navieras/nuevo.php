<?php
use App\Libraries\Csrf;
/** @var string|null $error */
/** @var string $nombre */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Nueva Naviera';
$pageSubtitle = 'Registrar una linea naviera';
$backHref = '/navieras';
require __DIR__ . '/../partials/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/navieras" class="bg-white rounded-xl shadow p-6 max-w-xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('globe', 'Datos de la naviera', 'sky') ?>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre</label>
        <input type="text" name="nombre" required maxlength="120" placeholder="Ej: EVERGREEN" value="<?= htmlspecialchars($nombre) ?>"
               class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 uppercase focus:ring-2 focus:ring-amber-400 focus:outline-none">
        <p class="text-xs text-zinc-500 mt-1">Se guarda en mayusculas.</p>
    </div>
    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
            <?= icon_small('check') ?><span>Registrar naviera</span>
        </button>
        <a href="/navieras" class="inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-6 py-2.5 font-medium hover:bg-slate-300 transition">
            <span>Cancelar</span>
        </a>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
