<?php
use App\Libraries\Csrf;
/** @var \Courier\Cliente\Domain\Cliente $cliente */
$pageTitle = 'Editar Cliente';
$pageSubtitle = $cliente->codigo() ?? '';
$backHref = '/clientes/' . $cliente->id();
require __DIR__ . '/../partials/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/clientes/<?= htmlspecialchars($cliente->id()) ?>/editar" class="bg-white rounded-xl shadow p-6 max-w-xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('pencil', 'Datos del cliente', 'amber') ?>
    <?php require __DIR__ . '/_form_campos.php'; ?>
    <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
            <?= icon_small('check') ?><span>Guardar cambios</span>
        </button>
        <a href="/clientes/<?= htmlspecialchars($cliente->id()) ?>" class="inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-6 py-2.5 font-medium hover:bg-slate-300 transition">
            <span>Cancelar</span>
        </a>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
