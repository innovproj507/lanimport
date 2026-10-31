/**
 * Cliente para el Agente de Impresion LAN (herramientas/agente-impresion de este proyecto),
 * un servicio propio que corre en http://localhost:9898 y hace de puente
 * entre el navegador y las impresoras instaladas en este equipo.
 * Reemplaza la dependencia de QZ Tray por una solucion propia del sistema.
 */
var LocalPrint = (function () {
    var BASE_URL = 'http://localhost:9898';
    var LS_ETIQUETAS = 'impresora_etiquetas';
    var LS_DOCUMENTOS = 'impresora_documentos';

    function estado() {
        return fetch(BASE_URL + '/estado').then(function (response) {
            if (!response.ok) {
                throw new Error('El agente de impresion respondio con error.');
            }

            return response.json();
        }).catch(function () {
            throw new Error('No se detecto el Agente de Impresion LAN corriendo en este equipo.');
        });
    }

    function listarImpresoras() {
        return fetch(BASE_URL + '/impresoras').then(function (response) {
            return response.json();
        }).then(function (data) {
            return data.impresoras || [];
        });
    }

    // Sin respuesta del agente (no instalado o cerrado), fetch solo dice "Failed to fetch".
    var SIN_AGENTE = 'No se pudo conectar con el Agente de Impresion LAN en este equipo. '
        + 'Si ya esta instalado, espere un minuto (se vuelve a abrir solo) o reinicie la sesion de Windows; '
        + 'si no, descarguelo desde Impresoras e instalelo.';

    function esperar(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    // La imagen se manda al agente ya descargada (del cache del navegador): asi el agente no
    // tiene que bajarla otra vez de internet. Si no se puede leer, el agente usa la URL.
    function imagenBase64(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) { throw new Error(); }
            return r.blob();
        }).then(function (blob) {
            return new Promise(function (resolve, reject) {
                var lector = new FileReader();
                lector.onload = function () { resolve(String(lector.result).split(',')[1] || ''); };
                lector.onerror = reject;
                lector.readAsDataURL(blob);
            });
        }).catch(function () { return ''; });
    }

    // Si el agente se esta reiniciando no responde por unos segundos: se reintenta antes de avisar.
    function enviarAlAgente(cuerpo, intentos) {
        return fetch(BASE_URL + '/imprimir-imagen', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: cuerpo
        }).catch(function () {
            if (intentos > 1) {
                return esperar(2000).then(function () { return enviarAlAgente(cuerpo, intentos - 1); });
            }
            throw new Error(SIN_AGENTE);
        });
    }

    function imprimirImagen(printerName, imageUrl) {
        if (!printerName) {
            return Promise.reject(new Error('No hay impresora de etiquetas configurada.'));
        }

        return imagenBase64(imageUrl).then(function (imagen) {
            return enviarAlAgente(JSON.stringify({ impresora: printerName, url: imageUrl, imagen: imagen }), 4);
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || data.error) {
                    throw new Error(data.error || 'No se pudo imprimir.');
                }

                return data;
            });
        });
    }

    function getImpresoraEtiquetas() {
        return localStorage.getItem(LS_ETIQUETAS) || '';
    }

    function setImpresoraEtiquetas(nombre) {
        localStorage.setItem(LS_ETIQUETAS, nombre);
    }

    function getImpresoraDocumentos() {
        return localStorage.getItem(LS_DOCUMENTOS) || '';
    }

    function setImpresoraDocumentos(nombre) {
        localStorage.setItem(LS_DOCUMENTOS, nombre);
    }

    return {
        estado: estado,
        listarImpresoras: listarImpresoras,
        imprimirImagen: imprimirImagen,
        getImpresoraEtiquetas: getImpresoraEtiquetas,
        setImpresoraEtiquetas: setImpresoraEtiquetas,
        getImpresoraDocumentos: getImpresoraDocumentos,
        setImpresoraDocumentos: setImpresoraDocumentos
    };
})();
