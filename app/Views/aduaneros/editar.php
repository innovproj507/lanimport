<?php
use App\Libraries\Csrf;
/** @var \Courier\Aduanero\Domain\Aduanero $aduanero */
/** @var array<int, \Courier\Pais\Domain\Pais> $paises */
$paises = $paises ?? [];
$pageTitle = 'Editar Aduanero';
$pageSubtitle = $aduanero->identificador();
$backHref = '/aduaneros';
require __DIR__ . '/../partials/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/aduaneros/<?= htmlspecialchars($aduanero->id()) ?>/editar" class="bg-white rounded-xl shadow p-6 max-w-xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('shield', 'Datos del aduanero', 'amber') ?>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Identificador</label>
        <input type="text" value="<?= htmlspecialchars($aduanero->identificador()) ?>" disabled
               class="w-full rounded-lg border border-zinc-300 bg-slate-100 text-zinc-500 px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre</label>
        <input type="text" name="nombre" required value="<?= htmlspecialchars($aduanero->nombre()) ?>"
               class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
    </div>
    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Pais</label>
        <select name="pais" required class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none">
            <option value="">Seleccione pais...</option>
            <?php foreach ($paises as $pais): ?>
                <option value="<?= htmlspecialchars($pais->nombre()) ?>" <?= $aduanero->pais() === $pais->nombre() ? 'selected' : '' ?>>
                    <?= htmlspecialchars($pais->nombre()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
            <?= icon_small('check') ?><span>Guardar cambios</span>
        </button>
        <a href="/aduaneros" class="inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-6 py-2.5 font-medium hover:bg-slate-300 transition">
            <span>Cancelar</span>
        </a>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
