<?php
/** @var array{tipo: string, texto: string}|null $flash mensaje de BaseController::flash() */
$flash ??= null;
if ($flash !== null):
    $estilo = $flash['tipo'] === 'ok'
        ? 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30'
        : 'bg-red-500/10 text-red-600 border-red-500/30';
?>
    <div class="<?= $estilo ?> text-sm rounded-lg px-4 py-3 mb-4 border flex items-start justify-between gap-3">
        <span><?= htmlspecialchars($flash['texto']) ?></span>
        <button type="button" onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100" title="Cerrar">&times;</button>
    </div>
<?php endif; ?>
