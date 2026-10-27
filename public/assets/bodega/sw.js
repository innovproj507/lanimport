/**
 * Service worker de la app de bodega (se sirve como /bodega/sw.js, alcance /bodega).
 * Guarda los archivos de la app (CSS, JS, iconos) para que abra rapido con mala senal.
 * La pagina /bodega (lleva la sesion y el token CSRF) y la API siempre van a la red.
 * Al cambiar archivos de la app, subir VERSION para renovar la cache.
 */
var VERSION = 'lan-bodega-v11';
var ARCHIVOS = [
    '/assets/bodega/css/app.css',
    '/assets/bodega/js/api.js',
    '/assets/bodega/js/escaner.js',
    '/assets/bodega/js/app.js',
    '/assets/bodega/manifest.webmanifest',
    '/assets/bodega/icons/icon-192.png',
    '/assets/bodega/icons/icon-512.png',
];

self.addEventListener('install', function (evento) {
    evento.waitUntil(caches.open(VERSION).then(function (cache) { return cache.addAll(ARCHIVOS); }));
    self.skipWaiting();
});

self.addEventListener('activate', function (evento) {
    evento.waitUntil(caches.keys().then(function (claves) {
        // Solo las de bodega: las lan-web-* son del service worker del sistema (/sw.js).
        return Promise.all(claves.filter(function (c) { return c.indexOf('lan-bodega-') === 0 && c !== VERSION; }).map(function (c) { return caches.delete(c); }));
    }));
    self.clients.claim();
});

self.addEventListener('fetch', function (evento) {
    var url = new URL(evento.request.url);

    if (evento.request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.indexOf('/assets/bodega/') !== 0) {
        return;
    }

    // Red primero (siempre la version nueva si hay conexion); la cache es el respaldo sin red.
    evento.respondWith(
        fetch(evento.request).then(function (respuesta) {
            var copia = respuesta.clone();
            caches.open(VERSION).then(function (cache) { cache.put(evento.request, copia); });

            return respuesta;
        }).catch(function () {
            return caches.match(evento.request);
        })
    );
});
