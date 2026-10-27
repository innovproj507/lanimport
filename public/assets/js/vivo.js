/**
 * Actualizacion automatica de pantallas: cada pocos segundos pregunta al servidor la "huella"
 * de los datos (/vivo/huella, muy liviana) y solo si cambio refresca lo que se ve.
 *
 * - Zonas: elementos con id y data-vivo-region="<recurso>" (global, salida:{id}). Al cambiar ese
 *   recurso se vuelve a pedir la pagina (o data-vivo-url, si la zona la trae) y se reemplaza
 *   su contenido. Una zona que el usuario esta usando (escribiendo, con un "Sacar" abierto o con
 *   cambios sin guardar) se deja para la siguiente vuelta.
 * - Listados por AJAX: Vivo.alCambiar('global', function () { return recargar(); }).
 * - Eventos: 'vivo:antes' y 'vivo:actualizado' (detail.region) para que la pagina reaplique su
 *   estado (p.ej. filas desplegadas).
 * - Intervalo: data-vivo-segundos en <body> (por defecto 30). Se pausa con la pestaña oculta.
 */
var Vivo = (function () {
    var oyentes = [];
    var ultimas = null;
    var ocupado = false;
    var detenido = false;

    function alCambiar(recurso, fn) {
        oyentes.push({ recurso: recurso, fn: fn });
    }

    function zonas() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-vivo-region][id]'));
    }

    function recursos() {
        var lista = zonas().map(function (z) { return z.getAttribute('data-vivo-region'); })
            .concat(oyentes.map(function (o) { return o.recurso; }));

        return lista.filter(function (r, i) { return r && lista.indexOf(r) === i; });
    }

    function enUso(zona) {
        var activo = document.activeElement;

        if (activo && activo !== document.body && zona.contains(activo) && /^(INPUT|SELECT|TEXTAREA)$/.test(activo.tagName)) {
            return true;
        }

        return zona.hasAttribute('data-vivo-sucio') || zona.querySelector('details[open]') !== null;
    }

    function indicar(estado) {
        var el = document.getElementById('vivo-indicador');

        if (!el) {
            return;
        }

        var textos = { ok: 'En vivo', actualizado: 'Actualizado', sinred: 'Sin conexion', sinsesion: 'Sesion cerrada' };
        el.querySelector('[data-vivo-texto]').textContent = textos[estado];
        el.setAttribute('data-estado', estado);

        if (estado === 'actualizado') {
            clearTimeout(el._t);
            el._t = setTimeout(function () { indicar('ok'); }, 2500);
        }
    }

    /** Vuelve a pedir las zonas de los recursos que cambiaron. Devuelve false si alguna quedo pendiente. */
    function refrescarZonas(cambiados) {
        var porUrl = {};

        zonas().forEach(function (z) {
            if (cambiados.indexOf(z.getAttribute('data-vivo-region')) === -1) {
                return;
            }

            var url = z.getAttribute('data-vivo-url') || window.location.href;
            (porUrl[url] = porUrl[url] || []).push(z);
        });

        return Promise.all(Object.keys(porUrl).map(function (url) {
            return fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) {
                    // Redirigido (p.ej. al login) o error: no se toca nada.
                    if (!r.ok || r.redirected) { throw new Error('vivo'); }
                    return r.text();
                })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var completo = true;

                    porUrl[url].forEach(function (zona) {
                        var nueva = doc.getElementById(zona.id);

                        if (!nueva) {
                            return;
                        }

                        if (enUso(zona)) {
                            completo = false;
                            return;
                        }

                        document.dispatchEvent(new CustomEvent('vivo:antes', { detail: { region: zona } }));
                        zona.innerHTML = nueva.innerHTML;
                        document.dispatchEvent(new CustomEvent('vivo:actualizado', { detail: { region: zona } }));
                    });

                    return completo;
                });
        })).then(function (r) { return r.indexOf(false) === -1; });
    }

    function consultar() {
        var lista = recursos();

        if (detenido || ocupado || document.hidden || lista.length === 0) {
            return;
        }

        ocupado = true;

        fetch('/vivo/huella?r=' + encodeURIComponent(lista.join(',')), {
            credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' },
        })
            .then(function (r) {
                if (r.status === 401) {
                    detenido = true;
                    indicar('sinsesion');
                    throw new Error('vivo');
                }
                return r.json();
            })
            .then(function (datos) {
                var huellas = datos.huellas || {};

                if (ultimas === null) {
                    ultimas = huellas;
                    indicar('ok');
                    return;
                }

                var cambiados = lista.filter(function (r) { return huellas[r] !== ultimas[r]; });

                if (cambiados.length === 0) {
                    indicar('ok');
                    return;
                }

                var tareas = [refrescarZonas(cambiados)].concat(oyentes
                    .filter(function (o) { return cambiados.indexOf(o.recurso) !== -1; })
                    .map(function (o) { return Promise.resolve(o.fn()); }));

                return Promise.all(tareas).then(function (r) {
                    // Si una zona estaba en uso, se reintenta en la proxima vuelta.
                    if (r[0] !== false) {
                        ultimas = huellas;
                    }
                    indicar('actualizado');
                });
            })
            .catch(function () {
                if (!detenido) { indicar(navigator.onLine === false ? 'sinred' : 'ok'); }
            })
            .then(function () { ocupado = false; });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var segundos = parseInt(document.body.getAttribute('data-vivo-segundos'), 10) || 30;

        // Lo que el usuario escribe en una zona no se pisa hasta que lo guarde.
        document.addEventListener('input', function (e) {
            var zona = e.target.closest && e.target.closest('[data-vivo-region]');
            if (zona) { zona.setAttribute('data-vivo-sucio', '1'); }
        });

        // Primera huella enseguida (punto de partida) y luego cada N segundos.
        setTimeout(consultar, 300);
        setInterval(consultar, segundos * 1000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { consultar(); } });
        window.addEventListener('online', consultar);
    });

    return { alCambiar: alCambiar, consultar: consultar };
})();
