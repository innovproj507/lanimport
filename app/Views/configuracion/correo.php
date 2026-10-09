<?php
use App\Libraries\Csrf;
use Courier\Configuracion\Domain\ConfiguracionSmtp;
/** @var ConfiguracionSmtp $smtp */
/** @var bool $smtpGuardado */
/** @var array<string, mixed>|null $entrada */
/** @var string|null $error */
/** @var string|null $mensaje */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Correo (SMTP)';
$pageSubtitle = 'Servidor de correo para las notificaciones del sistema';
$backHref = '/configuracion';
require __DIR__ . '/../partials/header.php';

$valor = static fn (string $campo, mixed $guardado): string => (string) ($entrada !== null ? ($entrada[$campo] ?? '') : ($guardado ?? ''));
$cifradoActual = $valor('cifrado', $smtp->cifrado);
$inputClass = 'w-full rounded-lg border border-zinc-300 bg-white text-black px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none';
?>

<?php require __DIR__ . '/_mensajes.php'; ?>

<?php if (!$smtpGuardado): ?>
    <div class="bg-amber-500/10 text-amber-700 text-sm rounded-lg px-4 py-3 mb-4 border border-amber-500/30 max-w-2xl">
        Todavia no se ha guardado una configuracion desde el sistema: los correos usan los valores <code>MAIL_*</code> del archivo <code>.env</code>, que se muestran abajo.
        Al guardar, el sistema pasara a usar esta configuracion.
    </div>
<?php endif; ?>

<form method="post" action="/configuracion/correo" class="bg-white rounded-xl shadow-sm p-6 max-w-2xl space-y-4" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('mail', 'Servidor SMTP', 'amber') ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-zinc-700 mb-1">Servidor</label>
            <input type="text" name="host" required placeholder="smtp.gmail.com" value="<?= htmlspecialchars($valor('host', $smtp->host)) ?>" class="<?= $inputClass ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Puerto</label>
            <input type="number" name="puerto" required min="1" max="65535" value="<?= htmlspecialchars($valor('puerto', $smtp->puerto)) ?>" class="<?= $inputClass ?>">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-zinc-700 mb-1">Cifrado</label>
        <select name="cifrado" class="<?= $inputClass ?>">
            <?php foreach (ConfiguracionSmtp::CIFRADOS as $clave => $etiqueta): ?>
                <option value="<?= htmlspecialchars($clave) ?>" <?= $clave === $cifradoActual ? 'selected' : '' ?>><?= htmlspecialchars($etiqueta) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Usuario</label>
            <input type="text" name="usuario" placeholder="Vacio si el servidor no pide autenticacion" value="<?= htmlspecialchars($valor('usuario', $smtp->usuario)) ?>" class="<?= $inputClass ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Contrasena</label>
            <input type="password" name="password" autocomplete="new-password"
                   placeholder="<?= $smtp->password ? 'Guardada — dejar en blanco para no cambiar' : 'Sin contrasena' ?>" class="<?= $inputClass ?>">
            <?php if ($smtp->password): ?>
                <label class="flex items-center gap-2 text-xs text-zinc-500 mt-1">
                    <input type="checkbox" name="borrar_password" value="1" class="rounded border-zinc-300 text-amber-500 focus:ring-amber-400">
                    Borrar la contrasena guardada
                </label>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Correo del remitente</label>
            <input type="email" name="remitente_email" required value="<?= htmlspecialchars($valor('remitente_email', $smtp->remitenteEmail)) ?>" class="<?= $inputClass ?>">
        </div>
        <div>
            <label class="block text-sm font-medium text-zinc-700 mb-1">Nombre del remitente</label>
            <input type="text" name="remitente_nombre" required value="<?= htmlspecialchars($valor('remitente_nombre', $smtp->remitenteNombre)) ?>" class="<?= $inputClass ?>">
        </div>
    </div>

    <p class="text-xs text-zinc-500">La contrasena se guarda cifrada y nunca se muestra en pantalla.</p>

    <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-black rounded-lg px-6 py-2.5 font-medium hover:bg-amber-400 transition">
        <?= icon_small('check') ?><span>Guardar configuracion</span>
    </button>
</form>

<form method="post" action="/configuracion/correo/probar" class="bg-white rounded-xl shadow-sm p-6 max-w-2xl space-y-3 mt-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <?= card_header('check', 'Probar envio', 'emerald') ?>
    <p class="text-sm text-zinc-500">Envia un correo de prueba con la configuracion <strong>guardada</strong>. Guarde primero si hizo cambios.</p>
    <div class="flex items-center gap-2">
        <input type="email" name="destinatario" required placeholder="correo@ejemplo.com" class="<?= $inputClass ?>">
        <button type="submit" class="inline-flex items-center gap-2 bg-emerald-500 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-emerald-600 transition whitespace-nowrap">
            <?= icon_small('mail') ?><span>Enviar prueba</span>
        </button>
    </div>
</form>

<?php require __DIR__ . '/../partials/footer.php'; ?>
