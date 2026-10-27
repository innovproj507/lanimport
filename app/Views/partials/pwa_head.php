<?php // App instalable (PWA) en PC y celular: manifest, iconos y color de la barra. Va dentro de <head>. ?>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0b1b4d">
    <link rel="apple-touch-icon" href="/assets/pwa/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="LAN Import">
    <script src="/assets/js/pwa.js?v=<?= filemtime(FCPATH . 'assets/js/pwa.js') ?>" defer></script>
