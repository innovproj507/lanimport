<?php
use App\Libraries\Csrf;
/** @var \Courier\Carga\Domain\Carga $carga */
/** @var array<int, array{id: string, codigo: string, estado: string, devolvible: bool}> $bultos */
/** @var int $bultosDeclarados */
/** @var string|null $error */
require_once __DIR__ . '/../partials/view_helpers.php';
$tracking = (string) $carga->trackingNumero();
$pageTitle = 'Devolver al proveedor';
$pageSubtitle = 'Tracking: ' . $tracking . ($carga->proveedor() !== '' ? ' · Proveedor: ' . $carga->proveedor() : '');
$backHref = '/cargas/' . $carga->id();
require __DIR__ . '/../partials/header.php';

// Por que un bulto no se puede devolver ahora.
$razonNoDevolvible = [
    'reserved' => 'En una orden de salida (saquelo primero de la OS/DO)',
    'in_repack' => 'En reempaque',
    'in_transfer' => 'En traslado',
    'consumed' => 'Ya salio / consumido',
    'returned' => 'Ya devuelto al proveedor',
];
$devolvibles = array_values(array_filter($bultos, static fn ($b) => $b['devolvible']));
$extensiones = implode(',', array_map(static fn ($e) => '.' . $e, \Courier\Carga\Application\AdjuntarDocumentoDevolucion\AdjuntarDocumentoDevolucionHandler::EXTENSIONES));
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="/cargas/<?= htmlspecialchars($carga->id()) ?>/devolver-proveedor" enctype="multipart/form-data" id="form-devolucion" class="space-y-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">

    <div class="bg-orange-50 border border-orange-200 text-orange-900 text-sm rounded-xl px-4 py-3">
        La carga <strong>no se borra</strong>: los bultos devueltos salen del inventario y quedan en el historial con el motivo y los documentos.
        Si se devuelven todos, la carga queda <strong>Devuelta al proveedor</strong>; si es parcial, el resto sigue en bodega.
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <?= card_header('box', 'Bultos que se devuelven', 'orange') ?>

        <?php if ($bultos === []): ?>
            <p class="text-sm text-zinc-700">La carga no tiene etiquetas (LPN): se devuelve <strong>completa</strong> (<?= $bultosDeclarados ?> bulto(s) declarados).</p>
        <?php else: ?>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3 text-sm">
                <label class="inline-flex items-center gap-2 font-medium text-black">
                    <input type="checkbox" id="dev-todos" class="rounded border-zinc-300 text-orange-500 focus:ring-orange-400" checked <?= $devolvibles === [] ? 'disabled' : '' ?>>
                    Todos los que estan en bodega
                </label>
                <span class="text-zinc-600"><strong id="dev-contador"><?= count($devolvibles) ?></strong> de <?= count($bultos) ?> bultos marcados</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 max-h-96 overflow-y-auto">
                <?php foreach ($bultos as $b): ?>
                    <label class="flex items-center gap-2 border rounded-lg px-3 py-2 text-sm <?= $b['devolvible'] ? 'border-zinc-200 hover:bg-orange-50 cursor-pointer' : 'border-slate-100 bg-slate-50 text-zinc-400' ?>">
                        <input type="checkbox" name="lpns[]" value="<?= htmlspecialchars($b['id']) ?>" class="dev-lpn rounded border-zinc-300 text-orange-500 focus:ring-orange-400"
                               <?= $b['devolvible'] ? 'checked' : 'disabled' ?>>
                        <span class="font-mono"><?= htmlspecialchars($b['codigo']) ?></span>
                        <?php if (!$b['devolvible']): ?>
                            <span class="text-xs ml-auto text-right"><?= htmlspecialchars($razonNoDevolvible[$b['estado']] ?? $b['estado']) ?></span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <?= card_header('pencil', 'Motivo y documentos', 'orange') ?>
        <label class="block">
            <span class="block text-sm font-medium text-black mb-1">Motivo de la devolucion <span class="text-red-600">*</span></span>
            <textarea name="motivo" rows="3" required minlength="3" maxlength="500" placeholder="Ej.: el cliente ya no quiere la mercancia; llego danada; pedido equivocado..."
                      class="w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 focus:outline-none"></textarea>
        </label>
        <label class="block">
            <span class="block text-sm font-medium text-black mb-1">Documentos (factura, nota de credito, guia, fotos...)</span>
            <input type="file" name="documentos[]" multiple accept="<?= htmlspecialchars($extensiones) ?>"
                   class="block w-full text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-orange-100 file:px-4 file:py-2 file:text-orange-800 hover:file:bg-orange-200">
            <span class="block text-xs text-zinc-500 mt-1">Puede elegir varios. PDF, imagen, Word, Excel, CSV o TXT; maximo 10 MB cada uno. Tambien se pueden agregar despues desde la carga.</span>
        </label>
        <p class="text-xs text-zinc-500">Si esta activado en Configuracion &gt; Notificaciones, se le avisa al cliente por correo con los bultos devueltos y el motivo.</p>
    </div>

    <div class="flex justify-end gap-2">
        <a href="/cargas/<?= htmlspecialchars($carga->id()) ?>" class="inline-flex items-center gap-2 bg-slate-200 text-zinc-800 rounded-lg px-4 py-2 text-sm font-medium hover:bg-slate-300 transition">Cancelar</a>
        <button type="submit" id="dev-enviar" class="inline-flex items-center gap-2 bg-orange-600 text-white rounded-lg px-5 py-2 text-sm font-medium hover:bg-orange-500 transition disabled:opacity-40 disabled:cursor-not-allowed">
            <?= icon_small('truck') ?><span>Devolver al proveedor</span>
        </button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('form-devolucion');
    var todos = document.getElementById('dev-todos');
    var checks = Array.prototype.slice.call(document.querySelectorAll('.dev-lpn:not(:disabled)'));
    var contador = document.getElementById('dev-contador');
    var enviar = document.getElementById('dev-enviar');
    var hayBultos = document.querySelectorAll('.dev-lpn').length > 0;

    function marcados() { return checks.filter(function (c) { return c.checked; }).length; }
    function actualizar() {
        if (!hayBultos) { return; }
        var n = marcados();
        contador.textContent = n;
        todos.checked = n === checks.length && n > 0;
        enviar.disabled = n === 0;
    }
    if (todos) {
        todos.addEventListener('change', function () { checks.forEach(function (c) { c.checked = todos.checked; }); actualizar(); });
    }
    checks.forEach(function (c) { c.addEventListener('change', actualizar); });
    actualizar();

    form.addEventListener('submit', function (e) {
        var texto = hayBultos
            ? 'Se devolveran ' + marcados() + ' bulto(s) al proveedor. Saldran del inventario y no se podran despachar. ¿Continuar?'
            : 'Se devolvera la carga completa al proveedor. ¿Continuar?';
        if (!confirm(texto)) { e.preventDefault(); }
    });
})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
