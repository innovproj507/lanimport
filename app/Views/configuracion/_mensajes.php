<?php
/** @var string|null $error */
/** @var string|null $mensaje */
?>
<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-2xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>
<?php if (!empty($mensaje)): ?>
    <div class="bg-emerald-500/10 text-emerald-700 text-sm rounded-lg px-4 py-3 mb-4 border border-emerald-500/30 max-w-2xl">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>
