<?php
use App\Libraries\AlertasMenu;

/**
 * Contenido de la campana (contador y lista de pendientes). Lo usan header.php y /vivo/campana,
 * que lo refresca solo cuando cambian los datos (vivo.js).
 * Deja $campanaTotalHtml y $campanaMenuHtml.
 */
$alertasMenu = AlertasMenu::paraRol($_SESSION['usuario_rol'] ?? null);
$totalAlertas = array_sum(array_column($alertasMenu, 'total'));

$campanaTotalHtml = $totalAlertas > 0
    ? '<span class="absolute top-1 right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">' . ($totalAlertas > 99 ? '99+' : $totalAlertas) . '</span>'
    : '';

ob_start();
if ($alertasMenu === []): ?>
    <p class="px-4 py-4 text-sm text-zinc-500">No hay pendientes.</p>
<?php else: ?>
    <?php foreach ($alertasMenu as $alerta): ?>
        <a href="<?= htmlspecialchars($alerta['href']) ?>" class="flex items-center justify-between px-4 py-3 text-sm text-zinc-700 hover:bg-slate-50">
            <span><?= htmlspecialchars($alerta['texto']) ?></span>
            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-600 text-xs font-semibold"><?= $alerta['total'] ?></span>
        </a>
    <?php endforeach; ?>
<?php endif;
$campanaMenuHtml = (string) ob_get_clean();
