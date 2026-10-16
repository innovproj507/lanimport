/**
 * Cliente de la API de bodega (/bodega/api/*). Misma sesion del sistema: la cookie va sola
 * y los POST llevan el token CSRF en la cabecera X-CSRF-Token. Si la sesion vencio, vuelve al login.
 */
(function () {
    var config = window.BLAN_CONFIG;

    function ErrorApi(mensaje, estado) {
        this.message = mensaje;
        this.estado = estado;
    }
    ErrorApi.prototype = Object.create(Error.prototype);

    function llamar(metodo, ruta, cuerpo) {
        var cabeceras = { 'Accept': 'application/json' };

        if (metodo !== 'GET') {
            cabeceras['Content-Type'] = 'application/json';
            cabeceras['X-CSRF-Token'] = config.csrf;
        }

        return fetch(config.apiBase + ruta, {
            method: metodo,
            headers: cabeceras,
            credentials: 'same-origin',
            body: cuerpo !== undefined ? JSON.stringify(cuerpo) : undefined,
        }).then(function (respuesta) {
            if (respuesta.status === 401) {
                window.location.href = '/login';
                throw new ErrorApi('Su sesion vencio. Entre de nuevo.', 401);
            }

            return respuesta.json().catch(function () {
                return { success: false, message: 'Respuesta invalida del servidor (' + respuesta.status + ').' };
            }).then(function (datos) {
                if (!respuesta.ok || !datos.success) {
                    throw new ErrorApi(datos.message || 'Error ' + respuesta.status, respuesta.status);
                }

                return datos.data;
            });
        }, function () {
            throw new ErrorApi('No hay conexion con el servidor.', 0);
        });
    }

    window.Api = {
        sesion: function () {
            return { nombre: config.nombre };
        },

        get: function (ruta) {
            return llamar('GET', ruta);
        },

        post: function (ruta, cuerpo) {
            return llamar('POST', ruta, cuerpo || {});
        },
    };
})();
