<?php
use App\Libraries\Csrf;
$pageTitle = 'Nuevo Cliente';
$pageSubtitle = 'Registrar un nuevo cliente';
require __DIR__ . '/../partials/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/clientes" class="bg-white rounded-xl shadow p-6 max-w-xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('users', 'Datos del cliente', 'emerald') ?>
    <?php require __DIR__ . '/_form_campos.php'; ?>
    <button type="submit" class="inline-flex items-center gap-2 bg-emerald-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-emerald-400 transition">
        <?= icon_small('check') ?><span>Registrar cliente</span>
    </button>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
