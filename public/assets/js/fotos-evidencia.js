/**
 * Fotos de evidencia (recepcion de cargas y salidas). Lo usan la app de bodega y la web.
 * Antes de subir, la foto se reduce en el equipo (lado mayor 1600 px, JPEG): una foto de
 * celular de 4-8 MB queda en unos 300-500 KB y sube rapido con el wifi de la bodega.
 */
(function () {
    var LADO_MAXIMO = 1600;
    var CALIDAD = 0.8;

    function cargarImagen(archivo) {
        if (window.createImageBitmap) {
            return createImageBitmap(archivo).catch(function () { return cargarConImg(archivo); });
        }

        return cargarConImg(archivo);
    }

    function cargarConImg(archivo) {
        return new Promise(function (resolver, rechazar) {
            var url = URL.createObjectURL(archivo);
            var img = new Image();
            img.onload = function () { URL.revokeObjectURL(url); resolver(img); };
            img.onerror = function () { URL.revokeObjectURL(url); rechazar(new Error('El archivo no es una foto.')); };
            img.src = url;
        });
    }

    /** Devuelve un Blob JPEG reducido; si no se puede reducir, la foto original. */
    function comprimir(archivo) {
        if (!archivo || !/^image\//.test(archivo.type)) {
            return Promise.reject(new Error('El archivo no es una foto.'));
        }

        return cargarImagen(archivo).then(function (img) {
            var ancho = img.width;
            var alto = img.height;
            var escala = Math.min(1, LADO_MAXIMO / Math.max(ancho, alto));
            var lienzo = document.createElement('canvas');
            lienzo.width = Math.round(ancho * escala);
            lienzo.height = Math.round(alto * escala);
            lienzo.getContext('2d').drawImage(img, 0, 0, lienzo.width, lienzo.height);

            return new Promise(function (resolver) {
                lienzo.toBlob(function (blob) {
                    resolver(blob && blob.size < archivo.size ? blob : archivo);
                }, 'image/jpeg', CALIDAD);
            });
        }, function () {
            // Formato que el navegador no sabe dibujar (p.ej. HEIC): se manda tal cual y decide el servidor.
            return archivo;
        });
    }

    /** Sube una foto a /bodega/api/{cargas|salidas}/{id}/fotos; resuelve con la lista de fotos. */
    function subir(url, archivo, csrf) {
        return comprimir(archivo).then(function (blob) {
            var datos = new FormData();
            datos.append('foto', blob, blob === archivo && archivo.name ? archivo.name : 'foto.jpg');

            return fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf },
                credentials: 'same-origin',
                body: datos,
            }).then(function (respuesta) {
                if (respuesta.status === 401) {
                    window.location.href = '/login';
                    throw new Error('Su sesion vencio. Entre de nuevo.');
                }

                return respuesta.json().catch(function () {
                    return { success: false, message: respuesta.status === 413 ? 'La foto es demasiado grande.' : 'Respuesta invalida del servidor (' + respuesta.status + ').' };
                }).then(function (r) {
                    if (!respuesta.ok || !r.success) {
                        throw new Error(r.message || 'No se pudo subir la foto.');
                    }

                    return r.data;
                });
            }, function () {
                throw new Error('No hay conexion con el servidor.');
            });
        });
    }

    /** Sube varias, una por una; avisa el avance con alAvanzar(hechas, total). */
    function subirVarias(url, archivos, csrf, alAvanzar) {
        var lista = Array.prototype.slice.call(archivos);
        var resultado = null;

        return lista.reduce(function (cadena, archivo, i) {
            return cadena.then(function () {
                if (alAvanzar) {
                    alAvanzar(i, lista.length);
                }

                return subir(url, archivo, csrf).then(function (fotos) { resultado = fotos; });
            });
        }, Promise.resolve()).then(function () {
            return resultado;
        });
    }

    window.FotosEvidencia = { comprimir: comprimir, subir: subir, subirVarias: subirVarias };

    // Web: <div data-fotos-subir="/bodega/api/cargas/ID/fotos" data-csrf="..."> con un input file y un [data-fotos-estado].
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-fotos-subir]').forEach(function (zona) {
            var input = zona.querySelector('input[type="file"]');
            var estado = zona.querySelector('[data-fotos-estado]');

            input.addEventListener('change', function () {
                if (input.files.length === 0) {
                    return;
                }

                input.disabled = true;
                estado.className = 'text-sm text-zinc-600';
                subirVarias(zona.getAttribute('data-fotos-subir'), input.files, zona.getAttribute('data-csrf'), function (i, total) {
                    estado.textContent = 'Subiendo ' + (i + 1) + ' de ' + total + '...';
                }).then(function () {
                    window.location.reload();
                }, function (error) {
                    input.disabled = false;
                    input.value = '';
                    estado.className = 'text-sm text-red-600';
                    estado.textContent = error.message;
                });
            });
        });
    });
})();
