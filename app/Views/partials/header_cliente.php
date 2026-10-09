<?php
use App\Libraries\Csrf;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$nombreActual = $_SESSION['usuario_nombre'] ?? null;

$pageTitle = $pageTitle ?? 'Portal de Cliente';
$pageSubtitle = $pageSubtitle ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
    <title><?= htmlspecialchars($pageTitle) ?> - LAN Import - Export S.A.</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-black">
<div class="min-h-screen flex flex-col">

    <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between shrink-0">
        <a href="/portal">
            <img src="/assets/images/logo.svg" alt="LAN Import - Export S.A." class="h-9 w-auto">
        </a>
        <div class="flex items-center gap-4">
            <?php if ($nombreActual): ?>
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-orange-500 text-white flex items-center justify-center text-sm font-semibold">
                        <?= htmlspecialchars(mb_strtoupper(mb_substr($nombreActual, 0, 1))) ?>
                    </span>
                    <span class="text-sm font-medium text-black"><?= htmlspecialchars($nombreActual) ?></span>
                </div>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <button type="submit" class="text-sm text-zinc-500 hover:text-orange-600 transition">Cerrar sesion</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <main class="flex-1 p-6">
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-black"><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if ($pageSubtitle): ?>
                    <p class="text-sm text-zinc-600"><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
