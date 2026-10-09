<?php
use App\Libraries\Csrf;
require_once __DIR__ . '/../partials/view_helpers.php';
/** @var string $token */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
    <title>Restablecer contrasena - LAN Import - Export S.A.</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen">

<div class="min-h-screen flex items-center justify-center lg:justify-end px-6 lg:pr-20 relative overflow-hidden">
    <div class="hidden lg:block absolute inset-0 bg-cover bg-center" style="background-image: url('/assets/images/fondo.png')"></div>

    <div class="relative z-10 w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <img src="/assets/images/logo.svg" alt="LAN Import - Export S.A." class="h-14 w-auto mx-auto mb-4">
            <h1 class="text-xl font-bold text-black">Restablecer contrasena</h1>
            <p class="text-zinc-600 text-sm mt-1">Ingresa tu nueva contrasena</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/reset-password/<?= htmlspecialchars($token) ?>" class="space-y-4">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Nueva contrasena</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-zinc-400"><?= icon_small('lock', 'w-5 h-5') ?></span>
                    <input type="password" name="password" id="reset-password" required minlength="8" placeholder="********"
                           class="w-full rounded-full border border-zinc-300 bg-white text-black placeholder-zinc-500 pl-11 pr-11 py-2.5 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    <button type="button" class="btn-toggle-password absolute inset-y-0 right-0 flex items-center pr-4 text-zinc-400 hover:text-zinc-700" data-target="reset-password">
                        <span class="icon-eye-show"><?= icon_small('eye', 'w-5 h-5') ?></span>
                        <span class="icon-eye-hide hidden"><?= icon_small('eye-slash', 'w-5 h-5') ?></span>
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1">Confirmar contrasena</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-zinc-400"><?= icon_small('lock', 'w-5 h-5') ?></span>
                    <input type="password" name="password_confirmacion" id="reset-password-confirmacion" required minlength="8" placeholder="********"
                           class="w-full rounded-full border border-zinc-300 bg-white text-black placeholder-zinc-500 pl-11 pr-11 py-2.5 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    <button type="button" class="btn-toggle-password absolute inset-y-0 right-0 flex items-center pr-4 text-zinc-400 hover:text-zinc-700" data-target="reset-password-confirmacion">
                        <span class="icon-eye-show"><?= icon_small('eye', 'w-5 h-5') ?></span>
                        <span class="icon-eye-hide hidden"><?= icon_small('eye-slash', 'w-5 h-5') ?></span>
                    </button>
                </div>
            </div>
            <button type="submit"
                    class="w-full bg-amber-500 text-black rounded-full py-2.5 font-medium hover:bg-amber-400 transition">
                Restablecer contrasena
            </button>
        </form>
    </div>
</div>

<script src="/assets/js/app.js?v=<?= filemtime(FCPATH . 'assets/js/app.js') ?>"></script>
</body>
</html>
