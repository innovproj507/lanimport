<?php
use App\Libraries\Csrf;
/** @var string $nombre usuario de la sesion */
$v = static fn (string $archivo): string => '/assets/bodega/' . $archivo . '?v=' . @filemtime(FCPATH . 'assets/bodega/' . $archivo);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1d4f">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="LAN Bodega">
    <title>LAN Bodega</title>
    <link rel="manifest" href="/assets/bodega/manifest.webmanifest">
    <link rel="icon" href="/assets/bodega/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/assets/bodega/icons/icon-192.png">
    <link rel="stylesheet" href="<?= $v('css/app.css') ?>">
</head>
<body>
    <header id="barra" class="barra">
        <button type="button" id="btn-inicio" class="barra-marca" aria-label="Inicio">
            <img src="/assets/bodega/icons/icon-192.png" alt="" width="32" height="32">
            <span>LAN Bodega</span>
        </button>
        <div class="barra-usuario">
            <span id="usuario-nombre"></span>
            <form method="post" action="/logout">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button type="submit" class="btn-texto">Salir</button>
            </form>
        </div>
    </header>

    <div id="sin-conexion" class="aviso-conexion oculto">Sin conexion. Los escaneos no se guardan hasta que vuelva la red.</div>

    <main id="vista" class="vista" aria-live="polite"></main>

    <div id="camara" class="camara oculto" role="dialog" aria-label="Escanear con la camara">
        <video id="camara-video" playsinline muted></video>
        <div class="camara-marco"></div>
        <p id="camara-texto" class="camara-texto">Apunte al codigo de barras de la etiqueta</p>
        <button type="button" id="camara-cerrar" class="btn btn-claro camara-cerrar">Cerrar camara</button>
    </div>

    <script>
        window.BLAN_CONFIG = <?= json_encode([
            'apiBase' => '/bodega/api',
            'csrf' => Csrf::token(),
            'nombre' => $nombre,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    </script>
    <script src="/assets/js/lector-codigos.js?v=<?= @filemtime(FCPATH . 'assets/js/lector-codigos.js') ?>"></script>
    <script src="<?= $v('js/api.js') ?>"></script>
    <script src="<?= $v('js/escaner.js') ?>"></script>
    <script src="<?= $v('js/app.js') ?>"></script>
</body>
</html>
