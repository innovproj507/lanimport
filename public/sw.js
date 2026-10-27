/**
 * Service worker del sistema (alcance /). La app de bodega tiene el suyo (/bodega/sw.js) y
 * dentro de /bodega manda ese.
 *
 * - Las paginas y los datos SIEMPRE vienen del servidor (nunca se muestra informacion vieja);
 *   sin conexion se muestra /offline.html.
 * - Solo se guardan archivos fijos: los de /assets con ?v= (cambian de URL al cambiar) se sirven
 *   de la cache; el resto de /assets y Tailwind (CDN) van a la red y la cache es el respaldo.
 * - Al cambiar este archivo o offline.html, subir VERSION para renovar la cache.
 */
var VERSION = 'lan-web-v1';
var PRECARGA = [
    '/offline.html',
    '/assets/images/logo.svg',
    '/assets/pwa/icon-192.png',
];

self.addEventListener('install', function (evento) {
    evento.waitUntil(caches.open(VERSION).then(function (cache) { return cache.addAll(PRECARGA); }));
    self.skipWaiting();
});

self.addEventListener('activate', function (evento) {
    evento.waitUntil(caches.keys().then(function (claves) {
        return Promise.all(claves.filter(function (c) { return c.indexOf('lan-web-') === 0 && c !== VERSION; })
            .map(function (c) { return caches.delete(c); }));
    }));
    self.clients.claim();
});

function guardar(peticion, respuesta) {
    if (!respuesta || (!respuesta.ok && respuesta.type !== 'opaque')) {
        return;
    }

    var copia = respuesta.clone();
    caches.open(VERSION).then(function (cache) {
        var url = new URL(peticion.url);

        // Un archivo versionado reemplaza a sus versiones anteriores (app.js?v=1 -> app.js?v=2).
        if (url.searchParams.has('v')) {
            cache.keys().then(function (claves) {
                claves.forEach(function (c) {
                    var otra = new URL(c.url);
                    if (otra.pathname === url.pathname && otra.search !== url.search) { cache.delete(c); }
                });
            });
        }

        cache.put(peticion, copia);
    });
}

function redPrimero(peticion) {
    return fetch(peticion).then(function (respuesta) {
        guardar(peticion, respuesta);
        return respuesta;
    }).catch(function () {
        return caches.match(peticion);
    });
}

self.addEventListener('fetch', function (evento) {
    var peticion = evento.request;

    if (peticion.method !== 'GET') {
        return;
    }

    var url = new URL(peticion.url);

    // Paginas: siempre del servidor; sin conexion, el aviso.
    if (peticion.mode === 'navigate') {
        evento.respondWith(fetch(peticion).catch(function () { return caches.match('/offline.html'); }));
        return;
    }

    if (url.origin === self.location.origin && url.pathname.indexOf('/assets/') === 0 && url.pathname.indexOf('/assets/bodega/') !== 0) {
        if (url.searchParams.has('v')) {
            evento.respondWith(caches.match(peticion).then(function (enCache) { return enCache || redPrimero(peticion); }));
        } else {
            evento.respondWith(redPrimero(peticion));
        }
        return;
    }

    if (url.hostname === 'cdn.tailwindcss.com') {
        evento.respondWith(redPrimero(peticion));
    }

    // Todo lo demas (datos, /vivo, /bodega/api, uploads, impresion local) no pasa por aqui.
});
