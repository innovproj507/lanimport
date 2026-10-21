/**
 * Lectura de codigos de barras desde un lector tipo teclado (PDA, pistola USB/Bluetooth).
 *
 * Cada lector termina el codigo de forma distinta segun su configuracion: Enter, Tab,
 * un salto de linea dentro del texto, o nada. Aqui se aceptan todas:
 *  - Enter / Tab (por key o por keyCode, que algunas PDA envian como "Unidentified").
 *  - Salto de linea o tabulador escrito dentro del valor.
 *  - Sin sufijo: si el texto ya tiene forma de codigo del sistema (LPN-XXXXXX-N o
 *    LAN-AAAA-XXXXXX, o CRX-AAAA-XXXXXX de cargas anteriores) y el lector dejo de escribir, se envia solo.
 *
 * Uso: LectorCodigos.conectar(input, function (codigo) { ... }, { autoEnviar: function () { return true; } })
 */
var LectorCodigos = (function () {
    // Con separadores que el lector puede cambiar segun la distribucion del teclado (- ' ? espacio).
    var FORMATO_CODIGO = /^\s*(LPN.?[0-9A-F]{6}.?\d{1,4}|(?:LAN|CRX).?\d{4}.?[0-9A-F]{6})\s*$/i;
    var PAUSA_MS = 450;

    function conectar(input, alLeer, opciones) {
        opciones = opciones || {};
        var autoEnviar = opciones.autoEnviar || function () { return true; };
        var temporizador = null;

        function enviar() {
            clearTimeout(temporizador);
            var codigo = input.value.replace(/[\r\n\t]+/g, '').trim();
            input.value = '';

            if (codigo !== '') {
                alLeer(codigo);
            }
        }

        input.addEventListener('keydown', function (evento) {
            var esFin = evento.key === 'Enter' || evento.key === 'Tab' || evento.keyCode === 13 || evento.keyCode === 9;

            if (!esFin) {
                return;
            }

            // Tab no debe sacar el foco del campo: el siguiente bulto se escanea aqui mismo.
            if (input.value.trim() !== '' || evento.key === 'Enter' || evento.keyCode === 13) {
                evento.preventDefault();
            }

            if (input.value.trim() !== '') {
                enviar();
            }
        });

        input.addEventListener('input', function () {
            clearTimeout(temporizador);

            if (/[\r\n\t]/.test(input.value)) {
                enviar();
                return;
            }

            if (autoEnviar() && FORMATO_CODIGO.test(input.value)) {
                temporizador = setTimeout(enviar, PAUSA_MS);
            }
        });

        return { enviar: enviar };
    }

    /**
     * Devuelve el foco al campo de escaneo si se pierde (el lector escribe donde este el foco),
     * salvo que el usuario haya pasado a otro campo, lista o boton a proposito.
     */
    function mantenerFoco(input) {
        input.addEventListener('blur', function () {
            setTimeout(function () {
                var activo = document.activeElement;

                if (activo && activo !== document.body && activo.closest('input, select, textarea, button, a, [contenteditable]')) {
                    return;
                }

                if (document.body.contains(input) && !input.disabled) {
                    input.focus({ preventScroll: true });
                }
            }, 150);
        });
    }

    return { conectar: conectar, mantenerFoco: mantenerFoco, FORMATO_CODIGO: FORMATO_CODIGO };
})();
