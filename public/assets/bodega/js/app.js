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
        // Si la camara esta abierta tapa la pantalla: el resultado se repite sobre la imagen.
        window.Escaner.mostrarResultado(texto, tipo);
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
            + '  <a class="menu-item menu-reempaque" href="#/reempaques"><span class="menu-icono">📦</span><span class="menu-titulo">Reempaque</span><span class="menu-detalle" id="cuenta-reempaques">Ordenes de trafico</span></a>'
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

        window.Api.get('/reempaques').then(function (ordenes) {
            var el = document.getElementById('cuenta-reempaques');

            if (el) {
                el.textContent = ordenes.length > 0 ? ordenes.length + ' orden(es) por armar' : 'Nada pendiente';
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
        // Carga desplegada (la ultima escaneada o la que se toco) y sus bultos sin escanear.
        var abierta = null;
        var faltanPorCarga = {};

        function etiquetaBulto(b) {
            return b.numero ? b.numero + '/' + b.total : '';
        }

        function pintarFaltan(c) {
            var faltan = faltanPorCarga[c.cargaId];
            var cuantos = c.total - c.recibidos;

            if (cuantos <= 0) {
                return '';
            }

            var html = '<button type="button" class="btn-faltan' + (abierta === c.cargaId ? ' abierto' : '') + '" data-faltan="' + esc(c.cargaId) + '">'
                + (abierta === c.cargaId ? '▾' : '▸') + ' Faltan ' + cuantos + ' bulto' + (cuantos === 1 ? '' : 's') + ' por escanear</button>';

            if (abierta !== c.cargaId) {
                return html;
            }

            if (!faltan) {
                return html + '<div class="faltan"><span class="vacio">Cargando...</span></div>';
            }

            return html + '<div class="faltan">' + faltan.map(function (b) {
                return '<span class="faltan-bulto"><b>' + esc(etiquetaBulto(b)) + '</b>' + esc(b.codigo)
                    + (b.descripcion ? '<small>' + esc(b.descripcion) + '</small>' : '') + '</span>';
            }).join('') + '</div>';
        }

        function abrir(cargaId) {
            abierta = cargaId;
            pintarCargas();

            return window.Api.get('/recepcion/' + encodeURIComponent(cargaId) + '/bultos').then(function (bultos) {
                faltanPorCarga[cargaId] = bultos.filter(function (b) { return !b.recibido; });
                pintarCargas();
            }).catch(function () {});
        }

        document.getElementById('rec-cargas').addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-faltan]');

            if (!boton) {
                return;
            }

            var id = boton.getAttribute('data-faltan');

            if (abierta === id) {
                abierta = null;
                pintarCargas();
            } else {
                abrir(id);
            }

            // El lector escribe donde este el foco: vuelve al campo de escaneo.
            input.focus();
        });

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
                        + '<div class="fila-progreso">' + barraProgreso(c.recibidos, c.total)
                        + '<a class="btn-foto" href="#' + rutaFotos('cargas', c.cargaId, c.tracking) + '">📷 ' + (c.fotos || 0) + '</a></div>'
                        + (completa ? '<div class="faltan-completa">✔ Todos los bultos escaneados</div>' : pintarFaltan(c))
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
                        var faltan = r.faltan || [];
                        texto += ' · ' + r.progreso.recibidos + ' de ' + r.progreso.total;

                        // Que bultos faltan de esa carga (los numeros de la etiqueta: 3/9, 5/9...).
                        if (faltan.length === 0) {
                            texto += ' · Carga completa';
                        } else {
                            var numeros = faltan.slice(0, 8).map(function (b) { return etiquetaBulto(b) || b.codigo; });
                            texto += ' · Faltan ' + faltan.length + ': ' + numeros.join(', ') + (faltan.length > 8 ? '…' : '');
                        }

                        // La carga escaneada queda desplegada con la lista de lo que falta.
                        faltanPorCarga[r.progreso.cargaId] = faltan;
                        abierta = r.progreso.cargaId;

                        var i = cargas.findIndex(function (c) { return c.cargaId === r.progreso.cargaId; });

                        if (i >= 0) {
                            r.progreso.fotos = cargas[i].fotos;
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
                // En consulta se cierra la camara para mostrar la ficha del bulto.
                window.Escaner.cerrar();
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
                    + (l.contenido && l.contenido.length ? '<dt>Contiene</dt><dd>' + l.contenido.length + ' bultos: ' + l.contenido.map(function (b) { return esc(b.codigo) + ' (' + esc(b.tracking) + ')'; }).join(', ') + '</dd>' : '')
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
                + '<section class="tarjeta"><h2>Cargas de la salida</h2>'
                + '  <p class="sub">📷 Fotos de la carga de cada cliente al subirla al contenedor.</p>'
                + '  <div id="sal-cargas" class="lista"></div></section>'
            );

            var avisoSal = document.getElementById('sal-aviso');

            var escaneado = {};
            orden.lineas.forEach(function (l) { escaneado[l.codigo] = l.escaneado; });

            // Carga a la que pertenece un codigo (para avisar "LAN-...: 5 de 11").
            function cargaDe(codigo) {
                return orden.cargas.find(function (c) { return c.codigos.indexOf(codigo) !== -1; });
            }

            function pintar() {
                var hechos = orden.lineas.filter(function (l) { return escaneado[l.codigo]; }).length;
                document.getElementById('sal-progreso').innerHTML = barraProgreso(hechos, orden.lineas.length);
                // Por carga: las que faltan primero, con sus bultos pendientes a la vista.
                document.getElementById('sal-cargas').innerHTML = orden.cargas
                    .map(function (c) {
                        var pendientes = c.codigos.filter(function (cod) { return !escaneado[cod]; });
                        return { c: c, pendientes: pendientes, listos: c.codigos.length - pendientes.length };
                    })
                    .sort(function (a, b) { return (a.pendientes.length === 0) - (b.pendientes.length === 0); })
                    .map(function (x) {
                        var completa = x.pendientes.length === 0;
                        return '<div class="fila' + (completa ? ' fila-completa' : '') + '">'
                            + '<div class="fila-principal"><strong>' + esc(x.c.tracking) + '</strong><span>' + esc(x.c.cliente) + (x.c.marca ? ' · ' + esc(x.c.marca) : '') + '</span>'
                            + (completa ? '<small>Completa</small>' : '<small>Faltan: ' + x.pendientes.map(esc).join(', ') + '</small>') + '</div>'
                            + '<div class="fila-progreso">' + barraProgreso(x.listos, x.c.codigos.length)
                            // Las cajas reempacadas no tienen carga propia: las fotos son por carga del cliente.
                            + (x.c.cargaId.indexOf('caja-') === 0 ? '' : '<a class="btn-foto" href="#' + rutaFotos('salidas', orden.id, x.c.tracking + ' · ' + x.c.cliente, x.c.cargaId) + '">📷 ' + (x.c.fotos || 0) + '</a>')
                            + '</div>'
                            + '</div>';
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
                    escaneado[r.codigo] = true;
                    pintar();
                    var carga = cargaDe(r.codigo);
                    var deCarga = carga ? carga.codigos.filter(function (cod) { return escaneado[cod]; }).length : 0;
                    aviso(avisoSal, r.codigo + ' cargado' + (carga ? ' · ' + carga.tracking + ': ' + deCarga + ' de ' + carga.codigos.length : '') + ' · Total ' + r.progreso.escaneadas + ' de ' + r.progreso.total, 'ok');
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

    function vistaReempaques() {
        html(cabecera('Reempaque', '/inicio') + '<section class="tarjeta"><h2>Ordenes de trafico</h2><div id="rep-lista" class="lista"><p class="vacio">Cargando...</p></div></section>');

        window.Api.get('/reempaques').then(function (ordenes) {
            document.getElementById('rep-lista').innerHTML = ordenes.length === 0
                ? '<p class="vacio">No hay ordenes de reempaque pendientes.</p>'
                : ordenes.map(function (o) {
                    return '<a class="fila fila-enlace" href="#/reempaques/' + encodeURIComponent(o.id) + '">'
                        + '<div class="fila-principal"><strong>' + esc(o.codigo) + ' · ' + esc(o.tipo) + '</strong><span>' + esc(o.cliente || '') + '</span>'
                        + '<small>' + esc(o.instrucciones || 'Sin instruccion') + '</small></div>'
                        + '<div class="fila-progreso">' + barraProgreso(o.progreso.empacados, o.progreso.total) + '</div>'
                        + '</a>';
                }).join('');
        }, function (error) {
            document.getElementById('rep-lista').innerHTML = '<p class="vacio">' + esc(error.message) + '</p>';
        });
    }

    /**
     * Bodega ejecuta la orden: abrir caja -> escanear lo que mete -> cerrar caja (se genera su
     * etiqueta, que se imprime en la PC) -> ... -> terminar cuando todo quedo empacado.
     */
    function vistaReempaque(id) {
        html(cabecera('Reempaque', '/reempaques') + '<p class="vacio">Cargando...</p>');
        var ruta = '/reempaques/' + encodeURIComponent(id);

        function accion(nombre, cuerpo) {
            return window.Api.post(ruta + '/' + nombre, cuerpo || {});
        }

        function pintar(orden, avisoTexto, avisoTipo) {
            limpiarVista.forEach(function (fn) { fn(); });
            limpiarVista = [];

            var abierta = orden.cajas.find(function (c) { return !c.cerrada; });
            var cerradas = orden.cajas.filter(function (c) { return c.cerrada; });
            var todoEmpacado = orden.pendientes.length === 0;

            html(
                cabecera(orden.codigo + ' · ' + orden.tipo, '/reempaques')
                + '<section class="tarjeta">'
                + '  <p class="sub">' + esc(orden.cliente || '') + '</p>'
                + (orden.instrucciones ? '  <p class="aviso aviso-ok">Trafico: ' + esc(orden.instrucciones) + '</p>' : '')
                + '  <div class="progreso-grande">' + barraProgreso(orden.progreso.empacados, orden.progreso.total) + '</div>'
                + '  <p id="rep-aviso" class="aviso oculto"></p>'
                + (abierta
                    ? '  <h2>Caja abierta: ' + esc(abierta.codigo) + ' <span class="contador">' + abierta.contenido.length + ' bultos</span></h2>'
                      + '  <div id="zona-escaneo"></div>'
                      + '  <button type="button" id="rep-cerrar" class="btn btn-primario btn-bloque">Cerrar caja</button>'
                      + (abierta.contenido.length === 0 ? '  <button type="button" id="rep-descartar" class="btn btn-claro btn-bloque">Descartar caja vacia</button>' : '')
                      + (abierta.contenido.length > 0 ? '  <ul class="lineas">' + abierta.contenido.map(function (c) {
                          return '<li class="hecho"><span>' + esc(c) + '</span><button type="button" class="btn-texto-oscuro" data-quitar="' + esc(c) + '">Quitar</button></li>';
                      }).join('') + '</ul>' : '')
                    : (todoEmpacado
                        ? '  <button type="button" id="rep-terminar" class="btn btn-primario btn-bloque">Terminar reempaque</button>'
                        : '  <button type="button" id="rep-abrir" class="btn btn-primario btn-bloque">Abrir caja nueva</button>'))
                + '</section>'
                + (orden.pendientes.length > 0
                    ? '<section class="tarjeta"><h2>Por empacar <span class="contador">' + orden.pendientes.length + '</span></h2><ul class="lineas">'
                      + orden.pendientes.map(function (p) { return '<li><span>' + esc(p.codigo) + '</span><span>' + esc(p.marca || '') + '</span></li>'; }).join('')
                      + '</ul></section>'
                    : '')
                + (cerradas.length > 0
                    ? '<section class="tarjeta"><h2>Cajas cerradas <span class="contador">' + cerradas.length + '</span></h2><div class="lista">'
                      + cerradas.map(function (c) {
                          return '<div class="fila fila-completa"><div class="fila-principal"><strong>' + esc(c.codigo) + '</strong>'
                              + '<small>' + c.contenido.length + ' bultos · etiqueta lista para imprimir en la PC</small></div></div>';
                      }).join('')
                      + '</div></section>'
                    : '')
            );

            var avisoRep = document.getElementById('rep-aviso');

            if (avisoTexto) {
                aviso(avisoRep, avisoTexto, avisoTipo);
            }

            function ejecutar(nombre, cuerpo, textoOk) {
                return accion(nombre, cuerpo).then(function (r) {
                    window.Escaner.pitido(true);
                    pintar(r.orden, typeof textoOk === 'function' ? textoOk(r.resultado) : textoOk, 'ok');
                }, function (error) {
                    aviso(avisoRep, error.message, 'error');
                    window.Escaner.pitido(false);
                });
            }

            var abrir = document.getElementById('rep-abrir');
            if (abrir) {
                abrir.addEventListener('click', function () {
                    ejecutar('abrir-caja', {}, function (r) { return 'Caja ' + r.codigo + ' abierta: escanee los bultos que mete.'; });
                });
            }

            var cerrar = document.getElementById('rep-cerrar');
            if (cerrar) {
                cerrar.addEventListener('click', function () {
                    ejecutar('cerrar-caja', {}, function (r) { return 'Caja ' + r.codigo + ' cerrada con ' + r.bultos + ' bultos. Imprima su etiqueta en la PC.'; });
                });
            }

            var descartar = document.getElementById('rep-descartar');
            if (descartar) {
                descartar.addEventListener('click', function () {
                    ejecutar('descartar-caja', {}, 'Caja descartada.');
                });
            }

            var terminar = document.getElementById('rep-terminar');
            if (terminar) {
                terminar.addEventListener('click', function () {
                    if (!confirm('¿Terminar el reempaque? Los bultos originales salen del inventario y quedan las cajas nuevas.')) {
                        return;
                    }
                    accion('terminar').then(function () {
                        alert('Reempaque ' + orden.codigo + ' terminado.');
                        ir('/reempaques');
                    }, function (error) {
                        aviso(avisoRep, error.message, 'error');
                    });
                });
            }

            vista.querySelectorAll('[data-quitar]').forEach(function (boton) {
                boton.addEventListener('click', function () {
                    ejecutar('quitar', { codigo: boton.getAttribute('data-quitar') }, boton.getAttribute('data-quitar') + ' quitado de la caja.');
                });
            });

            if (abierta) {
                campoEscaneo(document.getElementById('zona-escaneo'), function (codigo) {
                    return ejecutar('empacar', { codigo: codigo }, function (r) {
                        return r.codigo + ' en la caja (' + r.enCaja + ') · ' + r.empacados + ' de ' + r.total + ' empacados';
                    });
                }, 'Escanee cada bulto que mete en la caja');
            }
        }

        window.Api.get(ruta).then(function (orden) {
            pintar(orden);
        }, function (error) {
            html(cabecera('Reempaque', '/reempaques') + '<p class="aviso aviso-error">' + esc(error.message) + '</p>');
        });
    }

    // ---------- fotos de evidencia ----------

    /**
     * Fotos de la carga de un cliente: #/fotos/cargas/{cargaId}/{titulo} (recepcion) o
     * #/fotos/salidas/{ordenId}/{cargaId}/{titulo} (al subirla al contenedor). El titulo solo se muestra.
     */
    function rutaFotos(recurso, id, titulo, cargaId) {
        return '/fotos/' + recurso + '/' + encodeURIComponent(id) + (cargaId ? '/' + encodeURIComponent(cargaId) : '') + '/' + encodeURIComponent(titulo || '');
    }

    /**
     * Fotos de toda la carga del cliente (sus bultos/paletas desde varios angulos, no una por etiqueta):
     * tomar con la camara o elegir de la galeria; se reducen en el equipo y se suben una por una
     * (js/fotos-evidencia.js).
     */
    function vistaFotos(recurso, id, titulo, cargaId) {
        var esSalida = recurso === 'salidas';
        var volver = esSalida ? '/salidas/' + encodeURIComponent(id) : '/recepcion';
        var ruta = '/' + recurso + '/' + encodeURIComponent(id) + '/fotos' + (esSalida ? '?carga=' + encodeURIComponent(cargaId) : '');
        var url = window.BLAN_CONFIG.apiBase + ruta;

        html(
            cabecera(esSalida ? 'Fotos al cargar' : 'Fotos de recepcion', volver)
            + '<section class="tarjeta">'
            + '  <p class="sub"><strong>' + esc(titulo) + '</strong><br>' + (esSalida ? 'La carga del cliente al subirla al contenedor' : 'Estado en que llego la mercancia') + ': tome varias fotos desde diferentes angulos.</p>'
            + '  <div class="fotos-acciones">'
            + '    <label class="btn btn-primario">📷 Tomar foto<input id="foto-camara" type="file" accept="image/*" capture="environment" class="oculto"></label>'
            + '    <label class="btn btn-claro">🖼 Galeria<input id="foto-galeria" type="file" accept="image/*" multiple class="oculto"></label>'
            + '  </div>'
            + '  <p id="fotos-aviso" class="aviso oculto"></p>'
            + '</section>'
            + '<section class="tarjeta"><h2>Fotos <span id="fotos-cuenta" class="contador"></span></h2><div id="fotos-lista" class="fotos-grid"><p class="vacio">Cargando...</p></div></section>'
        );

        var avisoFotos = document.getElementById('fotos-aviso');

        function pintar(fotos) {
            document.getElementById('fotos-cuenta').textContent = fotos.length;
            document.getElementById('fotos-lista').innerHTML = fotos.length === 0
                ? '<p class="vacio">Todavia no hay fotos.</p>'
                : fotos.map(function (f) {
                    return '<a class="foto" href="' + esc(f.url) + '" data-foto-ver data-foto-texto="' + esc(f.fecha + (f.usuario ? ' · ' + f.usuario : '')) + '">'
                        + '<img src="' + esc(f.url) + '" alt="Foto de evidencia" loading="lazy">'
                        + '<small>' + esc(f.fecha) + (f.usuario ? ' · ' + esc(f.usuario) : '') + '</small></a>';
                }).join('');
        }

        function subir(input) {
            if (input.files.length === 0) {
                return;
            }

            var entradas = vista.querySelectorAll('.fotos-acciones input');
            entradas.forEach(function (e) { e.disabled = true; });

            window.FotosEvidencia.subirVarias(url, input.files, window.BLAN_CONFIG.csrf, function (i, total) {
                avisoFotos.textContent = 'Subiendo ' + (i + 1) + ' de ' + total + '...';
                avisoFotos.className = 'aviso aviso-ok';
            }).then(function (fotos) {
                pintar(fotos);
                avisoFotos.textContent = 'Foto guardada.';
                avisoFotos.className = 'aviso aviso-ok';
                window.Escaner.pitido(true);
            }, function (error) {
                avisoFotos.textContent = error.message;
                avisoFotos.className = 'aviso aviso-error';
                window.Escaner.pitido(false);
            }).then(function () {
                entradas.forEach(function (e) { e.disabled = false; });
                input.value = '';
            });
        }

        // Visor a pantalla completa (sin salir de la app): flechas o deslizar para pasar de foto.
        var visor = document.createElement('div');
        visor.className = 'visor oculto';
        visor.innerHTML = '<div class="visor-barra"><span data-visor-texto></span><button type="button" data-visor-cerrar aria-label="Cerrar">✕</button></div>'
            + '<img alt="Foto de evidencia">'
            + '<button type="button" class="visor-paso visor-ant" data-visor-paso="-1" aria-label="Anterior">‹</button>'
            + '<button type="button" class="visor-paso visor-sig" data-visor-paso="1" aria-label="Siguiente">›</button>';
        vista.appendChild(visor);
        var enlaces = [];
        var actual = 0;
        var inicioX = null;

        function verFoto(i) {
            actual = (i + enlaces.length) % enlaces.length;
            visor.querySelector('img').src = enlaces[actual].getAttribute('href');
            visor.querySelector('[data-visor-texto]').textContent = (actual + 1) + ' / ' + enlaces.length + '  ' + enlaces[actual].getAttribute('data-foto-texto');
            visor.querySelectorAll('.visor-paso').forEach(function (b) { b.classList.toggle('oculto', enlaces.length < 2); });
        }

        document.getElementById('fotos-lista').addEventListener('click', function (e) {
            var a = e.target.closest('[data-foto-ver]');

            if (!a) {
                return;
            }

            e.preventDefault();
            enlaces = Array.prototype.slice.call(this.querySelectorAll('[data-foto-ver]'));
            verFoto(enlaces.indexOf(a));
            visor.classList.remove('oculto');
        });

        visor.addEventListener('click', function (e) {
            var paso = e.target.closest('[data-visor-paso]');

            if (paso) {
                verFoto(actual + parseInt(paso.getAttribute('data-visor-paso'), 10));
            } else if (e.target === visor || e.target.closest('[data-visor-cerrar]')) {
                visor.classList.add('oculto');
            }
        });
        visor.addEventListener('touchstart', function (e) { inicioX = e.touches[0].clientX; }, { passive: true });
        visor.addEventListener('touchend', function (e) {
            var dx = inicioX === null ? 0 : e.changedTouches[0].clientX - inicioX;
            if (Math.abs(dx) > 50 && enlaces.length > 1) { verFoto(actual + (dx < 0 ? 1 : -1)); }
            inicioX = null;
        });

        document.getElementById('foto-camara').addEventListener('change', function () { subir(this); });
        document.getElementById('foto-galeria').addEventListener('change', function () { subir(this); });

        window.Api.get(ruta).then(pintar, function (error) {
            document.getElementById('fotos-lista').innerHTML = '<p class="vacio">' + esc(error.message) + '</p>';
        });
    }

    // ---------- navegacion ----------

    function enrutar() {
        limpiarVista.forEach(function (fn) { fn(); });
        limpiarVista = [];

        var ruta = location.hash.replace(/^#/, '') || '/inicio';
        document.getElementById('usuario-nombre').textContent = window.Api.sesion().nombre;

        var salida = ruta.match(/^\/salidas\/(.+)$/);
        var reempaque = ruta.match(/^\/reempaques\/(.+)$/);
        var fotosCarga = ruta.match(/^\/fotos\/cargas\/([^/]+)\/?(.*)$/);
        var fotosSalida = ruta.match(/^\/fotos\/salidas\/([^/]+)\/([^/]+)\/?(.*)$/);

        if (fotosCarga) {
            vistaFotos('cargas', decodeURIComponent(fotosCarga[1]), decodeURIComponent(fotosCarga[2]));
        } else if (fotosSalida) {
            vistaFotos('salidas', decodeURIComponent(fotosSalida[1]), decodeURIComponent(fotosSalida[3]), decodeURIComponent(fotosSalida[2]));
        } else if (ruta === '/recepcion') {
            vistaRecepcion();
        } else if (ruta === '/consulta') {
            vistaConsulta();
        } else if (ruta === '/salidas') {
            vistaSalidas();
        } else if (salida) {
            vistaSalida(decodeURIComponent(salida[1]));
        } else if (ruta === '/reempaques') {
            vistaReempaques();
        } else if (reempaque) {
            vistaReempaque(decodeURIComponent(reempaque[1]));
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
