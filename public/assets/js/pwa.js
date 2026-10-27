/**
 * App instalable (PWA): registra el service worker del sistema (/sw.js) y maneja los
 * botones "Instalar app" ([data-instalar-app], ocultos hasta que el navegador permite instalar).
 * El service worker solo funciona con HTTPS (o localhost).
 */
(function () {
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
        });
    }

    var instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    // iPhone/iPad no avisan cuando se puede instalar: se explica como hacerlo desde Safari.
    var esIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var aviso = null;

    function botones() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-instalar-app]'));
    }

    function mostrarBotones(visible) {
        botones().forEach(function (b) { b.classList.toggle('hidden', !visible); });
    }

    window.addEventListener('beforeinstallprompt', function (evento) {
        evento.preventDefault();
        aviso = evento;
        mostrarBotones(true);
    });

    window.addEventListener('appinstalled', function () {
        aviso = null;
        mostrarBotones(false);
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (!instalada && esIos) {
            mostrarBotones(true);
        }

        botones().forEach(function (boton) {
            boton.addEventListener('click', function () {
                if (aviso) {
                    aviso.prompt();
                    aviso.userChoice.then(function () { aviso = null; mostrarBotones(false); });
                } else if (esIos) {
                    alert('Para instalar LAN Import en este equipo:\n\n1. Abra esta pagina en Safari.\n2. Toque el boton Compartir (cuadro con flecha).\n3. Elija "Agregar a inicio".');
                }
            });
        });
    });
})();
