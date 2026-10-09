<?php
/** @var bool $smtpGuardado */
require_once __DIR__ . '/../partials/view_helpers.php';
$pageTitle = 'Configuracion';
$pageSubtitle = 'Ajustes generales del sistema';
require __DIR__ . '/../partials/header.php';

$secciones = [
    ['/usuarios', 'users', 'violet', 'Usuarios', 'Cuentas de acceso, roles y activacion de usuarios.'],
    ['/paises', 'globe', 'sky', 'Paises', 'Paises de destino y si muestran serie en la etiqueta LPN.'],
    ['/impresoras', 'printer', 'slate', 'Impresoras', 'Impresora de etiquetas de este equipo (se configura en cada estacion).'],
    ['/configuracion/correo', 'mail', 'amber', 'Correo (SMTP)', $smtpGuardado
        ? 'Servidor de correo para las notificaciones a clientes.'
        : 'Servidor de correo para las notificaciones. Hoy se usa el del archivo .env.'],
    ['/configuracion/notificaciones', 'bell', 'emerald', 'Notificaciones', 'Que correos automaticos envia el sistema.'],
    ['/configuracion/empresa', 'building', 'indigo', 'Datos de la empresa', 'Nombre, RUC, direccion y telefono que salen en los documentos impresos.'],
    ['/configuracion/etiqueta-lpn', 'box', 'rose', 'Etiqueta LPN', 'Que datos se imprimen en las etiquetas de bultos.'],
];
?>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 max-w-6xl">
    <?php foreach ($secciones as [$href, $icono, $color, $titulo, $descripcion]): ?>
        <a href="<?= htmlspecialchars($href) ?>" class="bg-white rounded-xl shadow-sm p-5 flex items-start gap-4 hover:shadow-md hover:ring-2 hover:ring-amber-300 transition">
            <span class="w-10 h-10 rounded-full bg-<?= $color ?>-500 text-white flex items-center justify-center shrink-0">
                <?= icon_small($icono, 'w-5 h-5') ?>
            </span>
            <span>
                <span class="block font-semibold text-black"><?= htmlspecialchars($titulo) ?></span>
                <span class="block text-sm text-zinc-500 mt-1"><?= htmlspecialchars($descripcion) ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
