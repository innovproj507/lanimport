<?php
use App\Libraries\Csrf;
/** @var \Courier\Configuracion\Domain\PreferenciasNotificacion $preferencias */
/** @var string|null $error */
/** @var string|null $mensaje */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Notificaciones';
$pageSubtitle = 'Correos automaticos que envia el sistema';
$backHref = '/configuracion';
require __DIR__ . '/../partials/header.php';
?>

<?php require __DIR__ . '/_mensajes.php'; ?>

<form method="post" action="/configuracion/notificaciones" class="bg-white rounded-xl shadow-sm p-6 max-w-2xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('bell', 'Correos al cliente', 'emerald') ?>

    <label class="flex items-start gap-3">
        <input type="checkbox" name="notificar_ingreso_carga" value="1" <?= $preferencias->notificarIngresoCarga ? 'checked' : '' ?>
               class="mt-1 rounded border-zinc-300 text-amber-500 focus:ring-amber-400">
        <span>
            <span class="block text-sm font-medium text-black">Avisar al cliente cuando su carga ingresa a bodega</span>
            <span class="block text-xs text-zinc-500">Se envia al registrar la carga en Ingreso de Carga, con el numero de tracking.</span>
        </span>
    </label>

    <div class="text-xs text-zinc-500 border-t border-slate-200 pt-3">
        Siempre se envian (no se pueden desactivar): las credenciales al aprobar un cliente y el enlace para recuperar contrasena.
    </div>

    <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
        <?= icon_small('check') ?><span>Guardar</span>
    </button>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
