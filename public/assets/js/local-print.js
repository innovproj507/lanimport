/**
 * Cliente para el Agente de Impresion LAN (herramientas/agente-impresion),
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

    function imprimirImagen(printerName, imageUrl) {
        if (!printerName) {
            return Promise.reject(new Error('No hay impresora de etiquetas configurada.'));
        }

        return fetch(BASE_URL + '/imprimir-imagen', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ impresora: printerName, url: imageUrl })
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
