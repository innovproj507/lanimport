<?php
use App\Libraries\AlertasMenu;
use App\Libraries\Csrf;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$rolActual = $_SESSION['usuario_rol'] ?? null;
$nombreActual = $_SESSION['usuario_nombre'] ?? null;
$puedeIngresar = in_array($rolActual, ['operaciones', 'bodega', 'gerente', 'admin'], true);
$puedeGestionar = in_array($rolActual, ['operaciones', 'gerente', 'admin'], true);
$esGerente = in_array($rolActual, ['gerente', 'admin'], true);
$esAdminOGerente = in_array($rolActual, ['gerente', 'admin'], true);
// El rol recepcion solo ve Recepcion de Carga (sin dashboard, rastreo ni buscador).
$esRecepcion = $rolActual === 'recepcion';
$inicio = $esRecepcion ? '/recepciones' : '/dashboard';

$pageTitle = $pageTitle ?? 'Courier';
$pageSubtitle = $pageSubtitle ?? '';
$headerActions = $headerActions ?? '';
$backHref = $backHref ?? null;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$alertasMenu = AlertasMenu::paraRol($rolActual);
$totalAlertas = array_sum(array_column($alertasMenu, 'total'));
$fechaHoy = class_exists(IntlDateFormatter::class)
    ? (new IntlDateFormatter('es', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, "d MMM yyyy"))->format(new DateTimeImmutable())
    : date('d/m/Y');

require_once __DIR__ . '/view_helpers.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
    <title><?= htmlspecialchars($pageTitle) ?> - LAN Import</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#eef2f9] text-black">
<div class="flex h-screen overflow-hidden">

    <div id="sidebar-backdrop" class="hidden fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

    <aside id="sidebar" class="hidden lg:flex fixed lg:static inset-y-0 left-0 z-40 w-64 bg-gradient-to-b from-[#0b1b4d] to-[#060f2e] flex-col shrink-0 shadow-xl">
        <div class="px-4 py-4 border-b border-white/10 flex items-center gap-3">
            <a href="<?= $inicio ?>" class="inline-flex items-center justify-center bg-white rounded-xl p-1.5 shadow-sm shrink-0">
                <img src="/assets/images/logo.svg" alt="LAN Import - Export S.A." class="h-9 w-auto">
            </a>
            <div class="leading-tight">
                <p class="text-white font-bold">LAN Import</p>
                <p class="text-amber-400 text-xs">Courier y Bodega</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5">
            <?php if ($esRecepcion): ?>
            <div>
                <p class="px-3 mb-2 text-[11px] font-semibold text-white/40 uppercase tracking-wider">Bodega</p>
                <div class="space-y-1">
                    <?= nav_item('/recepciones', 'receipt', 'Recepcion de Carga', $currentPath, false, 'text-red-400') ?>
                </div>
            </div>
            <?php else: ?>
            <div class="space-y-1">
                <?= nav_item('/dashboard', 'home', 'Dashboard', $currentPath, false, 'text-sky-400') ?>
            </div>

            <div>
                <p class="px-3 mb-2 text-[11px] font-semibold text-white/40 uppercase tracking-wider">Gestion de Carga</p>
                <div class="space-y-1">
                    <?php if ($puedeIngresar): ?>
                        <?= nav_item('/cargas/ingreso', 'box', 'Ingreso de Carga', $currentPath, false, 'text-amber-400') ?>
                        <?= nav_item('/recepciones', 'receipt', 'Recepcion de Carga', $currentPath, false, 'text-red-400') ?>
                        <?= nav_item('/inventario', 'box', 'Inventario', $currentPath, false, 'text-emerald-400') ?>
                        <?= nav_item('/salidas/historial', 'truck', 'Salidas', $currentPath, false, 'text-orange-400') ?>
                        <?= nav_item('/reempaques', 'box', 'Reempaque', $currentPath, false, 'text-violet-400') ?>
                    <?php endif; ?>
                    <?= nav_item('/tracking', 'search', 'Rastreo', $currentPath, false, 'text-cyan-400') ?>
                </div>
            </div>

            <?php if ($puedeGestionar): ?>
            <div>
                <p class="px-3 mb-2 text-[11px] font-semibold text-white/40 uppercase tracking-wider">Administracion</p>
                <div class="space-y-1">
                    <?= nav_item('/clientes', 'users', 'Clientes', $currentPath, false, 'text-pink-400') ?>
                    <?= nav_item('/aduaneros', 'shield', 'Aduaneros', $currentPath, false, 'text-yellow-400') ?>
                    <?= nav_item('/proveedores', 'truck', 'Proveedores', $currentPath, false, 'text-lime-400') ?>
                    <?= nav_item('/ubicaciones', 'box', 'Ubicaciones', $currentPath, false, 'text-teal-400') ?>
                    <?= nav_item('/facturas', 'receipt', 'Facturas', $currentPath, true) ?>
                    <?= nav_item('/reportes', 'chart', 'Reportes', $currentPath, false, 'text-rose-400') ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($puedeIngresar): ?>
            <div>
                <p class="px-3 mb-2 text-[11px] font-semibold text-white/40 uppercase tracking-wider">Configuracion</p>
                <div class="space-y-1">
                    <?php if ($esGerente): ?>
                        <?php
                        // Usuarios, Paises, Impresoras y los ajustes se abren desde las tarjetas de /configuracion.
                        $enConfiguracion = (bool) preg_match('#^/(configuracion|usuarios|paises|impresoras)(/|$)#', $currentPath);
                        ?>
                        <?= nav_item('/configuracion', 'cog', 'Configuracion', $enConfiguracion ? '/configuracion' : $currentPath, false, 'text-slate-300') ?>
                    <?php else: ?>
                        <?= nav_item('/impresoras', 'printer', 'Impresoras', $currentPath, false, 'text-amber-300') ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; /* fin menu completo (no recepcion) */ ?>
        </nav>

        <div class="px-4 py-3 border-t border-white/10 text-[11px] text-white/40">
            LAN Import - Export S.A.
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        <header class="bg-gradient-to-r from-[#0b1b4d] to-[#0f2a6b] shadow-md px-4 lg:px-6 py-3 flex items-center gap-3 shrink-0">
            <button type="button" id="btn-sidebar" title="Menu"
                    class="w-10 h-10 rounded-lg flex items-center justify-center text-white/80 hover:text-white hover:bg-white/10 transition shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
            </button>

            <?php if ($esRecepcion): ?>
                <a href="/recepciones" class="text-white font-semibold truncate lg:hidden">LAN Import</a>
            <?php else: ?>
            <form method="post" action="/tracking/buscar" class="hidden md:flex flex-1 max-w-md items-center bg-white rounded-full pl-4 pr-1.5 py-1 shadow-inner">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="text" name="tracking_numero" placeholder="Buscar numero de tracking..." autocomplete="off"
                       class="flex-1 bg-transparent text-sm text-black placeholder-zinc-400 focus:outline-none py-1.5">
                <button type="submit" title="Buscar" class="w-8 h-8 rounded-full flex items-center justify-center text-zinc-500 hover:bg-slate-100">
                    <?= icon_small('search') ?>
                </button>
            </form>
            <?php endif; ?>

            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                <span class="hidden sm:inline-flex items-center gap-2 bg-white text-zinc-700 text-sm rounded-lg px-3 py-1.5 shadow-sm">
                    <?= icon_small('clock', 'w-4 h-4 text-zinc-500') ?><?= htmlspecialchars($fechaHoy) ?>
                </span>

                <div class="relative" data-dropdown>
                    <button type="button" data-dropdown-toggle title="Notificaciones"
                            class="relative w-10 h-10 rounded-lg flex items-center justify-center text-white/80 hover:text-white hover:bg-white/10 transition">
                        <?= icon_small('bell', 'w-6 h-6') ?>
                        <?php if ($totalAlertas > 0): ?>
                            <span class="absolute top-1 right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center"><?= $totalAlertas > 99 ? '99+' : $totalAlertas ?></span>
                        <?php endif; ?>
                    </button>
                    <div data-dropdown-menu class="hidden absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-slate-200 z-50 overflow-hidden">
                        <p class="px-4 py-3 text-sm font-semibold text-black border-b border-slate-100">Pendientes</p>
                        <?php if ($alertasMenu === []): ?>
                            <p class="px-4 py-4 text-sm text-zinc-500">No hay pendientes.</p>
                        <?php else: ?>
                            <?php foreach ($alertasMenu as $alerta): ?>
                                <a href="<?= htmlspecialchars($alerta['href']) ?>" class="flex items-center justify-between px-4 py-3 text-sm text-zinc-700 hover:bg-slate-50">
                                    <span><?= htmlspecialchars($alerta['texto']) ?></span>
                                    <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-600 text-xs font-semibold"><?= $alerta['total'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($esGerente): ?>
                    <a href="/configuracion" title="Configuracion"
                       class="w-10 h-10 rounded-lg flex items-center justify-center text-white/80 hover:text-white hover:bg-white/10 transition">
                        <?= icon_small('cog', 'w-6 h-6') ?>
                    </a>
                <?php endif; ?>

                <button type="button" id="btn-fullscreen" title="Pantalla completa"
                        class="hidden sm:flex w-10 h-10 rounded-lg items-center justify-center text-white/80 hover:text-white hover:bg-white/10 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" /></svg>
                </button>

                <?php if ($nombreActual): ?>
                    <div class="relative pl-2 sm:pl-3 sm:border-l sm:border-white/20" data-dropdown>
                        <button type="button" data-dropdown-toggle class="flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-white/10 transition">
                            <span class="w-9 h-9 rounded-full bg-amber-500 text-white flex items-center justify-center text-sm font-semibold">
                                <?= htmlspecialchars(mb_strtoupper(mb_substr($nombreActual, 0, 1))) ?>
                            </span>
                            <span class="hidden sm:block text-left text-sm leading-tight">
                                <span class="block font-medium text-white"><?= htmlspecialchars($nombreActual) ?></span>
                                <span class="block text-amber-300 text-xs"><?= htmlspecialchars(ucfirst((string) $rolActual)) ?></span>
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="hidden sm:block w-4 h-4 text-white/70"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div data-dropdown-menu class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-200 z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-100">
                                <p class="text-sm font-semibold text-black"><?= htmlspecialchars($nombreActual) ?></p>
                                <p class="text-xs text-zinc-500">Rol: <?= htmlspecialchars(ucfirst((string) $rolActual)) ?></p>
                            </div>
                            <?php if ($esGerente): ?>
                                <a href="/configuracion" class="flex items-center gap-2 px-4 py-2.5 text-sm text-zinc-700 hover:bg-slate-50">
                                    <?= icon_small('cog', 'w-4 h-4 text-zinc-500') ?> Configuracion
                                </a>
                            <?php endif; ?>
                            <form method="post" action="/logout" class="border-t border-slate-100">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                <button type="submit" class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">
                                    <?= icon_small('logout', 'w-4 h-4') ?> Cerrar sesion
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 lg:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div class="flex items-center gap-3">
                    <?php if ($backHref): ?>
                        <a href="<?= htmlspecialchars($backHref) ?>" title="Volver"
                           class="w-9 h-9 rounded-lg flex items-center justify-center text-zinc-600 bg-white shadow-sm hover:bg-slate-100 transition shrink-0">
                            <?= icon_small('arrow-left', 'w-5 h-5') ?>
                        </a>
                    <?php endif; ?>
                    <div>
                        <h1 class="text-2xl font-bold text-[#0b1b4d]"><?= htmlspecialchars($pageTitle) ?></h1>
                        <?php if ($pageSubtitle): ?>
                            <p class="text-sm text-zinc-500"><?= htmlspecialchars($pageSubtitle) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($headerActions): ?>
                    <div class="flex flex-wrap items-center gap-3">
                        <?= $headerActions ?>
                    </div>
                <?php endif; ?>
            </div>
