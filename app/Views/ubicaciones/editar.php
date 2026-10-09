<?php
use App\Libraries\Csrf;
/** @var \Courier\Lpn\Domain\Ubicacion $ubicacion */
$pageTitle = 'Editar Ubicacion';
$pageSubtitle = $ubicacion->codigo();
$backHref = '/ubicaciones';
require __DIR__ . '/../partials/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/ubicaciones/<?= htmlspecialchars($ubicacion->id()) ?>/editar" class="bg-white rounded-xl shadow p-6 max-w-xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('box', 'Datos de la ubicacion', 'amber') ?>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Codigo</label>
        <input type="text" value="<?= htmlspecialchars($ubicacion->codigo()) ?>" disabled
               class="w-full rounded-lg border border-zinc-300 bg-slate-100 text-zinc-500 px-3 py-2">
    </div>
    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Zona (opcional)</label>
            <input type="text" name="zona" value="<?= htmlspecialchars($ubicacion->zona() ?? '') ?>"
                   class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Pasillo (opcional)</label>
            <input type="text" name="pasillo" value="<?= htmlspecialchars($ubicacion->pasillo() ?? '') ?>"
                   class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Nivel (opcional)</label>
            <input type="text" name="nivel" value="<?= htmlspecialchars($ubicacion->nivel() ?? '') ?>"
                   class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
        </div>
    </div>
    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
            <?= icon_small('check') ?><span>Guardar cambios</span>
        </button>
        <a href="/ubicaciones" class="inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-6 py-2.5 font-medium hover:bg-slate-300 transition">
            <span>Cancelar</span>
        </a>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
