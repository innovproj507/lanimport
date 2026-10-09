<?php
use App\Libraries\Csrf;
require_once __DIR__ . '/../partials/view_helpers.php';
/** @var bool $enviado */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
    <title>Recuperar contrasena - LAN Import - Export S.A.</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen">

<div class="min-h-screen flex items-center justify-center lg:justify-end px-6 lg:pr-20 relative overflow-hidden">
    <div class="hidden lg:block absolute inset-0 bg-cover bg-center" style="background-image: url('/assets/images/fondo.png')"></div>

    <div class="relative z-10 w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <img src="/assets/images/logo.svg" alt="LAN Import - Export S.A." class="h-14 w-auto mx-auto mb-4">
            <h1 class="text-xl font-bold text-black">Recuperar contrasena</h1>
            <p class="text-zinc-600 text-sm mt-1">Te enviaremos un enlace para restablecerla</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 text-red-600 text-sm rounded-lg px-4 py-3 mb-4 border border-red-500/30">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($enviado): ?>
            <div class="bg-emerald-500/10 text-emerald-700 text-sm rounded-lg px-4 py-3 mb-4 border border-emerald-500/30">
                Si el correo esta registrado, recibiras un enlace para restablecer tu contrasena en unos minutos.
            </div>
            <a href="/login" class="text-sm text-amber-600 hover:underline">&larr; Volver a iniciar sesion</a>
        <?php else: ?>
            <form method="post" action="/forgot-password" class="space-y-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1">Correo electronico</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-zinc-400"><?= icon_small('mail', 'w-5 h-5') ?></span>
                        <input type="email" name="email" required placeholder="info@example.com"
                               class="w-full rounded-full border border-zinc-300 bg-white text-black placeholder-zinc-500 pl-11 pr-4 py-2.5 focus:ring-2 focus:ring-amber-400 focus:outline-none">
                    </div>
                </div>
                <button type="submit"
                        class="w-full bg-amber-500 text-black rounded-full py-2.5 font-medium hover:bg-amber-400 transition">
                    Enviar enlace
                </button>
                <a href="/login" class="block text-center text-sm text-amber-600 hover:underline">&larr; Volver a iniciar sesion</a>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
