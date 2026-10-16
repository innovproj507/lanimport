<?php
use App\Libraries\Csrf;
/**
 * Botones Desactivar/Activar y Eliminar de una fila de catalogo (clientes, aduaneros, navieras).
 * Eliminar solo lo ven gerencia y owner (igual que el filtro de la ruta) y el servidor
 * lo rechaza si el registro ya se uso.
 *
 * @var string $base    ruta base del registro, ej. "/clientes/{id}"
 * @var bool   $activo
 * @var string $nombre  para el mensaje de confirmacion
 */
$puedeEliminar = in_array($_SESSION['usuario_rol'] ?? null, ['gerente', 'admin'], true);
$confirmar = static fn (string $texto): string => htmlspecialchars(json_encode($texto, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
?>
<?php if ($activo): ?>
    <form method="post" action="<?= htmlspecialchars($base) ?>/desactivar" class="inline" onsubmit="return confirm(<?= $confirmar("¿Desactivar {$nombre}? Dejara de aparecer en Ingreso de Carga y Salidas; su historial se conserva.") ?>);">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <?= icon_submit_button('eye-slash', 'Desactivar', 'slate') ?>
    </form>
<?php else: ?>
    <form method="post" action="<?= htmlspecialchars($base) ?>/activar" class="inline">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <?= icon_submit_button('check', 'Activar', 'emerald') ?>
    </form>
<?php endif; ?>
<?php if ($puedeEliminar): ?>
    <form method="post" action="<?= htmlspecialchars($base) ?>/eliminar" class="inline" onsubmit="return confirm(<?= $confirmar("¿Eliminar definitivamente {$nombre}? Solo se puede si nunca se uso en cargas ni salidas.") ?>);">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <?= icon_submit_button('trash', 'Eliminar', 'red') ?>
    </form>
<?php endif; ?>
