/**
 * Lectura de codigos de barras.
 * - PDA con lector (Zebra, Honeywell...): el lector "teclea" el codigo y manda Enter en el campo
 *   de escaneo. inputmode="none" evita que aparezca el teclado en pantalla.
 * - Tablet o telefono sin lector: camara con BarcodeDetector (Chrome en Android). Solo funciona
 *   con HTTPS; si no esta disponible, el boton de camara no se muestra.
 */
(function () {
    var detector = null;
    var flujo = null;
    var activo = false;
    var alLeer = null;
    var ultimo = { codigo: '', hora: 0 };

    var capa = document.getElementById('camara');
    var video = document.getElementById('camara-video');
    var texto = document.getElementById('camara-texto');

    function disponible() {
        return 'BarcodeDetector' in window && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) && window.isSecureContext;
    }

    function cerrar() {
        activo = false;

        if (flujo) {
            flujo.getTracks().forEach(function (pista) { pista.stop(); });
            flujo = null;
        }

        video.srcObject = null;
        capa.classList.add('oculto');
    }

    function buscar() {
        if (!activo) {
            return;
        }

        detector.detect(video).then(function (codigos) {
            if (codigos.length > 0) {
                var codigo = codigos[0].rawValue;
                var ahora = Date.now();

                // El mismo codigo queda frente a la camara varios cuadros seguidos: se lee una sola vez.
                if (codigo !== ultimo.codigo || ahora - ultimo.hora > 2500) {
                    ultimo = { codigo: codigo, hora: ahora };
                    texto.textContent = 'Leido: ' + codigo + ' — enviando...';
                    texto.className = 'camara-texto';
                    alLeer(codigo);
                }
            }
        }).catch(function () {
            // Cuadro sin imagen todavia; se reintenta en el siguiente.
        }).then(function () {
            if (activo) {
                setTimeout(buscar, 250);
            }
        });
    }

    function abrir(callback) {
        alLeer = callback;
        detector = detector || new window.BarcodeDetector({ formats: ['code_128', 'code_39', 'qr_code'] });
        texto.textContent = 'Apunte al codigo de barras de la etiqueta';
        texto.className = 'camara-texto';
        capa.classList.remove('oculto');

        return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then(function (s) {
                flujo = s;
                video.srcObject = s;
                activo = true;

                return video.play();
            })
            .then(buscar)
            .catch(function () {
                cerrar();
                throw new Error('No se pudo abrir la camara. Revise el permiso de camara del navegador.');
            });
    }

    document.getElementById('camara-cerrar').addEventListener('click', cerrar);

    /**
     * Resultado de la ultima lectura sobre la imagen de la camara: con la camara abierta,
     * los avisos de la pantalla quedan tapados.
     */
    function mostrarResultado(mensaje, tipo) {
        if (!activo) {
            return;
        }

        texto.textContent = mensaje;
        texto.className = 'camara-texto camara-texto-' + (tipo === 'ok' ? 'ok' : 'error');
    }

    var audio = null;

    function pitido(ok) {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();

            // El navegador crea el audio pausado si todavia no hubo un toque o tecla en la pagina.
            if (audio.state === 'suspended') {
                audio.resume();
            }

            var osc = audio.createOscillator();
            var gain = audio.createGain();
            osc.frequency.value = ok ? 880 : 220;
            gain.gain.value = 0.2;
            osc.connect(gain);
            gain.connect(audio.destination);
            osc.start();
            osc.stop(audio.currentTime + (ok ? 0.12 : 0.45));
        } catch (e) {
            // Sin audio: queda el aviso en pantalla.
        }

        if (!ok && navigator.vibrate) {
            navigator.vibrate([120, 60, 120]);
        }
    }

    window.Escaner = { disponible: disponible, abrir: abrir, cerrar: cerrar, pitido: pitido, mostrarResultado: mostrarResultado };
})();
