<?php
use App\Libraries\Csrf;
/** @var string|null $error */
require_once __DIR__ . '/../../partials/view_helpers.php';
$pageTitle = 'Carga masiva';
$pageSubtitle = 'Registrar varias cargas a la vez desde un archivo Excel';
$backHref = '/cargas/ingreso';
require __DIR__ . '/../../partials/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30 max-w-3xl">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="max-w-3xl space-y-6">
    <div class="bg-white rounded-2xl shadow-sm p-5 sm:p-6">
        <?= card_header('receipt', '1. Descargue la plantilla', 'indigo') ?>
        <p class="text-sm text-zinc-600 mb-4">
            Cada fila es una linea de mercancia. Las filas con la misma <strong>Referencia</strong> forman una sola carga.
            La plantilla trae una hoja de instrucciones y la lista de codigos de clientes, proveedores y aduaneros.
        </p>
        <a href="/cargas/masiva/plantilla" class="inline-flex items-center gap-2 bg-indigo-500 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-indigo-600 transition">
            <?= icon_small('arrow-left', 'w-4 h-4 -rotate-90') ?><span>Descargar plantilla Excel</span>
        </a>
    </div>

    <form method="post" action="/cargas/masiva/previsualizar" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-5 sm:p-6 space-y-5">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <?= card_header('box', '2. Suba el archivo lleno', 'amber') ?>

        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Archivo Excel (.xlsx) o CSV, hasta 5 MB</label>
            <input type="file" name="archivo" required accept=".xlsx,.csv"
                   class="block w-full text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-500 file:px-4 file:py-2 file:text-sm file:font-medium file:text-black hover:file:bg-amber-400">
        </div>

        <fieldset>
            <legend class="block text-sm font-medium text-zinc-700 mb-2">Acta de recepcion — ¿la mercancia ya esta fisicamente en bodega?</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="flex items-start gap-3 border border-zinc-300 rounded-xl p-4 cursor-pointer has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                    <input type="radio" name="acta" value="en_bodega" required class="mt-1 text-amber-500 focus:ring-amber-400">
                    <span>
                        <span class="block text-sm font-semibold text-black">Si, ya esta en bodega</span>
                        <span class="block text-xs text-zinc-500 mt-1">Se genera el acta automaticamente para cada carga, marcada "Generada por carga masiva". No quedan pendientes para bodega.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 border border-zinc-300 rounded-xl p-4 cursor-pointer has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                    <input type="radio" name="acta" value="pendiente" required class="mt-1 text-amber-500 focus:ring-amber-400">
                    <span>
                        <span class="block text-sm font-semibold text-black">No, todavia no llega</span>
                        <span class="block text-xs text-zinc-500 mt-1">Las cargas quedan en "Cargas registradas sin acta" y bodega llena el acta real (chofer, placa, cedula) cuando llegue.</span>
                    </span>
                </label>
            </div>
        </fieldset>

        <label class="flex items-start gap-3">
            <input type="checkbox" name="notificar" value="1" class="mt-1 rounded border-zinc-300 text-amber-500 focus:ring-amber-400">
            <span>
                <span class="block text-sm font-medium text-black">Enviar el correo de aviso a cada cliente</span>
                <span class="block text-xs text-zinc-500">Desmarcado por defecto, para no enviar correos masivos por error (por ejemplo al pasar cargas antiguas).</span>
            </span>
        </label>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center gap-3 pt-2">
            <button type="submit" class="inline-flex justify-center items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
                <?= icon_small('eye') ?><span>Revisar antes de guardar</span>
            </button>
            <span class="text-xs text-zinc-500">No se guarda nada hasta que confirme en el siguiente paso.</span>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
