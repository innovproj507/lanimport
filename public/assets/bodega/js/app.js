/**
 * LAN Bodega: app para PDA/tablet. Pantallas por hash (#/recepcion, #/salidas/ID...).
 * Todo dato viene de la API de LAN Import (js/api.js); aqui solo hay presentacion.
 */
(function () {
    var vista = document.getElementById('vista');
    var barra = document.getElementById('barra');
    var limpiarVista = [];

    // ---------- utilidades ----------

    function esc(texto) {
        return String(texto === null || texto === undefined ? '' : texto)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function html(cadena) {
        vista.innerHTML = cadena;
        window.scrollTo(0, 0);
    }

    function fecha(iso) {
        if (!iso) {
            return '';
        }

        var p = String(iso).slice(0, 10).split('-');

        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
    }

    function ir(ruta) {
        if (location.hash === '#' + ruta) {
            enrutar();
        } else {
            location.hash = ruta;
        }
    }

    function aviso(contenedor, texto, tipo) {
        contenedor.textContent = texto;
        contenedor.className = 'aviso aviso-' + tipo;
    }

    function barraProgreso(hechos, total) {
        var pct = total > 0 ? Math.round(hechos * 100 / total) : 0;

        return '<div class="progreso"><div class="progreso-barra" style="width:' + pct + '%"></div></div>'
            + '<span class="progreso-texto">' + hechos + ' / ' + total + '</span>';
    }

    /**
     * Campo de escaneo: recibe lo que manda el lector de la PDA, o la camara.
     * Procesa las lecturas en cola, una por una, para no perder ninguna si se escanea rapido.
     */
    function campoEscaneo(contenedor, procesar, etiqueta) {
        contenedor.innerHTML =
            '<label class="etiqueta" for="escaneo">' + esc(etiqueta) + '</label>'
            + '<div class="escaneo">'
            + '  <input id="escaneo" class="escaneo-input" type="text" autocomplete="off" autocorrect="off"'
            + '         autocapitalize="characters" spellcheck="false" inputmode="none" enterkeyhint="go"'
            + '         placeholder="Escanee la etiqueta">'
            + '  <button type="button" class="btn-icono" data-accion="teclado" title="Escribir el codigo a mano">⌨</button>'
            + (window.Escaner.disponible() ? '  <button type="button" class="btn-icono" data-accion="camara" title="Usar la camara">📷</button>' : '')
            + '</div>';

        var input = contenedor.querySelector('#escaneo');
        var cola = [];
        var ocupado = false;

        function siguiente() {
            if (ocupado || cola.length === 0) {
                return;
            }

            ocupado = true;
            Promise.resolve(procesar(cola.shift())).catch(function () {}).then(function () {
                ocupado = false;
                siguiente();
            });
        }

        function recibir(codigo) {
            codigo = String(codigo || '').trim();

            if (codigo !== '') {
                cola.push(codigo);
                siguiente();
            }
        }

        // Enter, Tab, salto de linea o lector sin sufijo (lector-codigos.js). Con el teclado
        // en pantalla activado (escritura a mano) solo envia con Enter.
        window.LectorCodigos.conectar(input, recibir, {
            autoEnviar: function () { return input.getAttribute('inputmode') === 'none'; },
        });

        contenedor.querySelector('[data-accion="teclado"]').addEventListener('click', function () {
            var manual = input.getAttribute('inputmode') === 'none';
            input.setAttribute('inputmode', manual ? 'text' : 'none');
            this.classList.toggle('activo', manual);
            input.blur();
            input.focus();
        });

        var botonCamara = contenedor.querySelector('[data-accion="camara"]');

        if (botonCamara) {
            botonCamara.addEventListener('click', function () {
                window.Escaner.abrir(recibir).catch(function (error) {
                    alert(error.message);
                });
            });
        }

        // El lector escribe donde este el foco: se devuelve al campo si se toca otra parte de la pantalla.
        function reenfocar(evento) {
            var destino = evento.target;

            if (!destino.closest('input, select, textarea, button, a') && document.getElementById('camara').classList.contains('oculto')) {
                input.focus();
            }
        }

        document.addEventListener('click', reenfocar);
        limpiarVista.push(function () {
            document.removeEventListener('click', reenfocar);
            window.Escaner.cerrar();
        });

        input.focus();

        return input;
    }

    function cabecera(titulo, volver) {
        return '<div class="cabecera">'
            + (volver ? '<button type="button" class="btn-volver" data-volver="' + esc(volver) + '" aria-label="Volver">‹</button>' : '')
            + '<h1>' + esc(titulo) + '</h1></div>';
    }

    vista.addEventListener('click', function (evento) {
        var volver = evento.target.closest('[data-volver]');

        if (volver) {
            ir(volver.getAttribute('data-volver'));
        }
    });

    // ---------- pantallas ----------

    function vistaInicio() {
        html(
            '<div class="saludo">Hola, ' + esc(window.Api.sesion().nombre) + '</div>'
            + '<nav class="menu">'
            + '  <a class="menu-item menu-recepcion" href="#/recepcion"><span class="menu-icono">⬇</span><span class="menu-titulo">Recepcion</span><span class="menu-detalle" id="cuenta-recepcion">Escanear etiquetas nuevas</span></a>'
            + '  <a class="menu-item menu-consulta" href="#/consulta"><span class="menu-icono">🔍</span><span class="menu-titulo">Consultar LPN</span><span class="menu-detalle">Ver estado y ubicacion de un bulto</span></a>'
            + '  <a class="menu-item menu-salidas" href="#/salidas"><span class="menu-icono">⬆</span><span class="menu-titulo">Salidas</span><span class="menu-detalle" id="cuenta-salidas">Despachar ordenes</span></a>'
            + '</nav>'
            + '<p class="pie"><a href="/recepcion">Ir a la version completa del sistema</a></p>'
        );

        window.Api.get('/recepcion').then(function (cargas) {
            var faltan = cargas.reduce(function (s, c) { return s + c.total - c.recibidos; }, 0);
            var el = document.getElementById('cuenta-recepcion');

            if (el) {
                el.textContent = faltan > 0 ? faltan + ' bultos por recibir en ' + cargas.length + ' carga(s)' : 'Nada pendiente';
            }
        }).catch(function () {});

        window.Api.get('/salidas').then(function (ordenes) {
            var el = document.getElementById('cuenta-salidas');

            if (el) {
                el.textContent = ordenes.length > 0 ? ordenes.length + ' orden(es) por despachar' : 'Nada pendiente';
            }
        }).catch(function () {});
    }

    function vistaRecepcion() {
        html(
            cabecera('Recepcion', '/inicio')
            + '<section class="tarjeta">'
            + '  <div id="zona-escaneo"></div>'
            + '  <label class="etiqueta" for="ubicacion">Ubicacion (opcional)</label>'
            + '  <select id="ubicacion" class="campo"><option value="">Sin ubicacion (bodega general)</option></select>'
            + '  <p id="rec-aviso" class="aviso oculto"></p>'
            + '  <ul id="rec-historial" class="historial"></ul>'
            + '</section>'
            + '<section class="tarjeta">'
            + '  <h2>Cargas por recibir <span id="rec-faltan" class="contador"></span></h2>'
            + '  <div id="rec-cargas" class="lista"><p class="vacio">Cargando...</p></div>'
            + '</section>'
        );

        var avisoRec = document.getElementById('rec-aviso');
        var historial = document.getElementById('rec-historial');
        var selectUbicacion = document.getElementById('ubicacion');
        var cargas = [];

        function pintarCargas() {
            var cont = document.getElementById('rec-cargas');

            if (!cont) {
                return;
            }

            var faltan = cargas.reduce(function (s, c) { return s + c.total - c.recibidos; }, 0);
            document.getElementById('rec-faltan').textContent = faltan > 0 ? faltan + ' bultos' : '';

            cont.innerHTML = cargas.length === 0
                ? '<p class="vacio">No hay etiquetas pendientes de recibir.</p>'
                : cargas.map(function (c) {
                    var completa = c.recibidos >= c.total;

                    return '<div class="fila' + (completa ? ' fila-completa' : '') + '">'
                        + '<div class="fila-principal"><strong>' + esc(c.tracking) + '</strong><span>' + esc(c.cliente) + (c.marca ? ' · ' + esc(c.marca) : '') + '</span>'
                        + '<small>Ingreso ' + esc(fecha(c.fechaIngreso)) + (completa ? ' · Completa' : '') + '</small></div>'
                        + '<div class="fila-progreso">' + barraProgreso(c.recibidos, c.total) + '</div>'
                        + '</div>';
                }).join('');
        }

        function anotar(texto, ok) {
            var li = document.createElement('li');
            li.className = ok ? 'ok' : 'error';
            li.textContent = new Date().toLocaleTimeString().slice(0, 5) + '  ' + texto;
            historial.insertBefore(li, historial.firstChild);

            while (historial.children.length > 6) {
                historial.removeChild(historial.lastChild);
            }
        }

        var input = campoEscaneo(document.getElementById('zona-escaneo'), function (codigo) {
            return window.Api.post('/recepcion/escanear', { codigo: codigo, ubicacion_id: selectUbicacion.value })
                .then(function (r) {
                    var texto = r.lpn.codigo + ' → ' + r.lpn.estadoLabel;

                    if (r.progreso) {
                        texto += ' · ' + r.progreso.recibidos + ' de ' + r.progreso.total
                            + (r.progreso.recibidos >= r.progreso.total ? ' · Carga completa' : '');

                        var i = cargas.findIndex(function (c) { return c.cargaId === r.progreso.cargaId; });

                        if (i >= 0) {
                            cargas[i] = r.progreso;
                        } else {
                            cargas.push(r.progreso);
                        }

                        pintarCargas();
                    }

                    aviso(avisoRec, texto, 'ok');
                    anotar(texto, true);
                    window.Escaner.pitido(true);
                }, function (error) {
                    aviso(avisoRec, error.message, 'error');
                    anotar(error.message, false);
                    window.Escaner.pitido(false);
                });
        }, 'Codigo del LPN');

        selectUbicacion.addEventListener('change', function () {
            input.focus();
        });

        window.Api.get('/ubicaciones').then(function (ubicaciones) {
            ubicaciones.forEach(function (u) {
                var op = document.createElement('option');
                op.value = u.id;
                op.textContent = u.codigo;
                selectUbicacion.appendChild(op);
            });
        }).catch(function () {});

        window.Api.get('/recepcion').then(function (datos) {
            cargas = datos;
            pintarCargas();
        }, function (error) {
            document.getElementById('rec-cargas').innerHTML = '<p class="vacio">' + esc(error.message) + '</p>';
        });
    }

    function vistaConsulta() {
        html(
            cabecera('Consultar LPN', '/inicio')
            + '<section class="tarjeta"><div id="zona-escaneo"></div><p id="con-aviso" class="aviso oculto"></p></section>'
            + '<section id="con-ficha"></section>'
        );

        var avisoCon = document.getElementById('con-aviso');

        campoEscaneo(document.getElementById('zona-escaneo'), function (codigo) {
            return window.Api.get('/lpns/' + encodeURIComponent(codigo)).then(function (l) {
                avisoCon.className = 'aviso oculto';
                window.Escaner.pitido(true);
                document.getElementById('con-ficha').innerHTML =
                    '<div class="tarjeta ficha">'
                    + '<div class="ficha-codigo">' + esc(l.codigo) + '</div>'
                    + '<span class="estado estado-' + esc(l.estado) + '">' + esc(l.estadoLabel) + '</span>'
                    + '<dl>'
                    + '<dt>Cliente</dt><dd>' + esc(l.cliente || '—') + '</dd>'
                    + '<dt>Marca</dt><dd>' + esc(l.marca || '—') + '</dd>'
                    + '<dt>Descripcion</dt><dd>' + esc(l.descripcion || '—') + '</dd>'
                    + '<dt>Ubicacion</dt><dd>' + esc(l.ubicacion || 'Bodega general') + '</dd>'
                    + (l.carga ? '<dt>Carga</dt><dd>' + esc(l.carga.tracking) + (l.bulto ? ' · bulto ' + esc(l.bulto) : '') + '</dd>'
                        + '<dt>Proveedor</dt><dd>' + esc(l.carga.proveedor) + '</dd>'
                        + '<dt>Ingreso</dt><dd>' + esc(fecha(l.carga.fechaIngreso)) + '</dd>' : '')
                    + '<dt>Ultimo cambio</dt><dd>' + esc(l.actualizado) + '</dd>'
                    + '</dl></div>';
            }, function (error) {
                aviso(avisoCon, error.message, 'error');
                window.Escaner.pitido(false);
                document.getElementById('con-ficha').innerHTML = '';
            });
        }, 'Escanee el LPN a consultar');
    }

    function vistaSalidas() {
        html(cabecera('Salidas', '/inicio') + '<section class="tarjeta"><h2>Ordenes por despachar</h2><div id="sal-lista" class="lista"><p class="vacio">Cargando...</p></div></section>');

        window.Api.get('/salidas').then(function (ordenes) {
            document.getElementById('sal-lista').innerHTML = ordenes.length === 0
                ? '<p class="vacio">No hay ordenes de salida pendientes.</p>'
                : ordenes.map(function (o) {
                    return '<a class="fila fila-enlace" href="#/salidas/' + encodeURIComponent(o.id) + '">'
                        + '<div class="fila-principal"><strong>' + esc(o.do) + '</strong><span>' + esc(o.cliente || '') + '</span>'
                        + '<small>' + (o.contenedor ? 'Contenedor ' + esc(o.contenedor) : 'Sin contenedor') + '</small></div>'
                        + '<div class="fila-progreso">' + barraProgreso(o.progreso.escaneadas, o.progreso.total) + '</div>'
                        + '</a>';
                }).join('');
        }, function (error) {
            document.getElementById('sal-lista').innerHTML = '<p class="vacio">' + esc(error.message) + '</p>';
        });
    }

    function vistaSalida(id) {
        html(cabecera('Salida', '/salidas') + '<p class="vacio">Cargando...</p>');

        window.Api.get('/salidas/' + encodeURIComponent(id)).then(function (orden) {
            html(
                cabecera(orden.do, '/salidas')
                + '<section class="tarjeta">'
                + '  <p class="sub">' + esc(orden.cliente || '') + (orden.contenedor ? ' · ' + esc(orden.contenedor) : '') + '</p>'
                + '  <div id="sal-progreso" class="progreso-grande"></div>'
                + (orden.estado === 'pendiente' ? '  <div id="zona-escaneo"></div>' : '<p class="aviso aviso-ok">Orden ' + esc(orden.estado) + '.</p>')
                + '  <p id="sal-aviso" class="aviso oculto"></p>'
                + (orden.estado === 'pendiente' ? '  <button type="button" id="sal-finalizar" class="btn btn-primario btn-bloque" disabled>Finalizar salida</button>' : '')
                + '</section>'
                + '<section class="tarjeta"><h2>LPNs de la orden</h2><ul id="sal-lineas" class="lineas"></ul></section>'
            );

            var avisoSal = document.getElementById('sal-aviso');

            function pintar() {
                var hechos = orden.lineas.filter(function (l) { return l.escaneado; }).length;
                document.getElementById('sal-progreso').innerHTML = barraProgreso(hechos, orden.lineas.length);
                document.getElementById('sal-lineas').innerHTML = orden.lineas
                    .slice()
                    .sort(function (a, b) { return a.escaneado - b.escaneado; })
                    .map(function (l) {
                        return '<li class="' + (l.escaneado ? 'hecho' : '') + '"><span>' + esc(l.codigo) + '</span><span>' + (l.escaneado ? '✓' : 'Falta') + '</span></li>';
                    }).join('');

                var finalizar = document.getElementById('sal-finalizar');

                if (finalizar) {
                    finalizar.disabled = hechos < orden.lineas.length;
                }
            }

            pintar();

            if (orden.estado !== 'pendiente') {
                return;
            }

            campoEscaneo(document.getElementById('zona-escaneo'), function (codigo) {
                return window.Api.post('/salidas/' + encodeURIComponent(id) + '/escanear', { codigo: codigo }).then(function (r) {
                    orden.lineas.forEach(function (l) {
                        if (l.codigo === r.codigo) {
                            l.escaneado = true;
                        }
                    });
                    pintar();
                    aviso(avisoSal, r.codigo + ' cargado · ' + r.progreso.escaneadas + ' de ' + r.progreso.total, 'ok');
                    window.Escaner.pitido(true);
                }, function (error) {
                    aviso(avisoSal, error.message, 'error');
                    window.Escaner.pitido(false);
                });
            }, 'Escanee cada bulto que sube al contenedor');

            document.getElementById('sal-finalizar').addEventListener('click', function () {
                if (!confirm('¿Finalizar la salida ' + orden.do + '? Los LPNs saldran del inventario.')) {
                    return;
                }

                this.disabled = true;
                window.Api.post('/salidas/' + encodeURIComponent(id) + '/finalizar').then(function () {
                    alert('Salida ' + orden.do + ' finalizada.');
                    ir('/salidas');
                }, function (error) {
                    aviso(avisoSal, error.message, 'error');
                    document.getElementById('sal-finalizar').disabled = false;
                });
            });
        }, function (error) {
            html(cabecera('Salida', '/salidas') + '<p class="aviso aviso-error">' + esc(error.message) + '</p>');
        });
    }

    // ---------- navegacion ----------

    function enrutar() {
        limpiarVista.forEach(function (fn) { fn(); });
        limpiarVista = [];

        var ruta = location.hash.replace(/^#/, '') || '/inicio';
        document.getElementById('usuario-nombre').textContent = window.Api.sesion().nombre;

        var salida = ruta.match(/^\/salidas\/(.+)$/);

        if (ruta === '/recepcion') {
            vistaRecepcion();
        } else if (ruta === '/consulta') {
            vistaConsulta();
        } else if (ruta === '/salidas') {
            vistaSalidas();
        } else if (salida) {
            vistaSalida(decodeURIComponent(salida[1]));
        } else {
            vistaInicio();
        }
    }

    document.getElementById('btn-inicio').addEventListener('click', function () {
        ir('/inicio');
    });

    function estadoRed() {
        document.getElementById('sin-conexion').classList.toggle('oculto', navigator.onLine);
    }

    window.addEventListener('online', estadoRed);
    window.addEventListener('offline', estadoRed);
    window.addEventListener('hashchange', enrutar);

    estadoRed();
    enrutar();

    if ('serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register('/bodega/sw.js', { scope: '/bodega' }).catch(function () {});
    }
})();
