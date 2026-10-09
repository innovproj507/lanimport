document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-toggle-password').forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute('data-target'));
        var showIcon = btn.querySelector('.icon-eye-show');
        var hideIcon = btn.querySelector('.icon-eye-hide');

        if (!input) {
            return;
        }

        btn.addEventListener('click', function () {
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            if (showIcon) {
                showIcon.classList.toggle('hidden', isPassword);
            }

            if (hideIcon) {
                hideIcon.classList.toggle('hidden', !isPassword);
            }
        });
    });
});

/**
 * Selector generico "escriba para buscar" (cliente, proveedor, etc.): filtra una lista
 * cargada en un <script type="application/json"> contra un termino escrito por el usuario,
 * y guarda el valor elegido en un <input type="hidden">.
 *
 * config: {
 *   pickerId, inputId, hiddenId, resultadosId, errorId (opcional), dataScriptId,
 *   valor: function(item) -> string,   // lo que se guarda en el hidden
 *   etiqueta: function(item) -> string, // lo que se muestra/busca
 *   formId (opcional): valida que el hidden no este vacio al enviar ese formulario
 * }
 * Devuelve { agregarYSeleccionar: function(item) } o null si el HTML no esta presente.
 */
function crearBuscadorSelector(config) {
    var picker = document.getElementById(config.pickerId);
    var buscarInput = document.getElementById(config.inputId);
    var idHidden = document.getElementById(config.hiddenId);
    var resultados = document.getElementById(config.resultadosId);
    var errorMsg = config.errorId ? document.getElementById(config.errorId) : null;
    var dataScript = document.getElementById(config.dataScriptId);

    if (!picker || !buscarInput || !idHidden || !resultados || !dataScript) {
        return null;
    }

    var items = JSON.parse(dataScript.textContent || '[]');

    function escaparHtml(texto) {
        var div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function ocultarResultados() {
        resultados.classList.add('hidden');
        resultados.innerHTML = '';
    }

    function seleccionar(item) {
        idHidden.value = config.valor(item);
        buscarInput.value = config.etiqueta(item);

        if (errorMsg) {
            errorMsg.classList.add('hidden');
        }

        ocultarResultados();
    }

    function buscar() {
        var termino = buscarInput.value.trim().toLowerCase();
        idHidden.value = '';

        if (termino === '') {
            ocultarResultados();
            return;
        }

        var coincidencias = items.filter(function (item) {
            return config.etiqueta(item).toLowerCase().indexOf(termino) !== -1;
        }).slice(0, 20);

        if (coincidencias.length === 0) {
            resultados.innerHTML = '<div class="px-3 py-2 text-sm text-zinc-500">Sin coincidencias.</div>';
            resultados.classList.remove('hidden');
            return;
        }

        resultados.innerHTML = coincidencias.map(function (item, i) {
            return '<div class="buscador-opcion px-3 py-2 text-sm text-black hover:bg-slate-100 cursor-pointer" data-index="' + i + '">'
                + escaparHtml(config.etiqueta(item)) + '</div>';
        }).join('');

        resultados.querySelectorAll('.buscador-opcion').forEach(function (el, i) {
            el.addEventListener('click', function () {
                seleccionar(coincidencias[i]);
            });
        });

        resultados.classList.remove('hidden');
    }

    buscarInput.addEventListener('input', buscar);
    buscarInput.addEventListener('focus', function () {
        if (buscarInput.value.trim() !== '' && idHidden.value === '') {
            buscar();
        }
    });

    buscarInput.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            ocultarResultados();
        }
    });

    document.addEventListener('click', function (event) {
        if (!picker.contains(event.target)) {
            ocultarResultados();
        }
    });

    if (config.formId) {
        var form = document.getElementById(config.formId);

        if (form) {
            form.addEventListener('submit', function (event) {
                if (idHidden.value === '') {
                    event.preventDefault();

                    if (errorMsg) {
                        errorMsg.classList.remove('hidden');
                    }

                    buscarInput.focus();
                }
            });
        }
    }

    return {
        agregarYSeleccionar: function (item) {
            items.push(item);
            seleccionar(item);
        },
    };
}

document.addEventListener('DOMContentLoaded', function () {
    window.ClientePicker = crearBuscadorSelector({
        pickerId: 'cliente-picker',
        inputId: 'cliente-buscar',
        hiddenId: 'cliente-id-hidden',
        resultadosId: 'cliente-resultados',
        errorId: 'cliente-error',
        dataScriptId: 'clientes-data',
        formId: 'form-ingreso-carga',
        valor: function (cliente) { return cliente.id; },
        etiqueta: function (cliente) {
            var partes = [];

            if (cliente.codigo) {
                partes.push(cliente.codigo);
            }

            partes.push(cliente.nombre);

            if (cliente.empresa) {
                partes.push(cliente.empresa);
            }

            if (cliente.email) {
                partes.push(cliente.email);
            }

            return partes.join(' — ');
        },
    });

    window.ProveedorPicker = crearBuscadorSelector({
        pickerId: 'proveedor-picker',
        inputId: 'proveedor-buscar',
        hiddenId: 'proveedor-id-hidden',
        resultadosId: 'proveedor-resultados',
        errorId: 'proveedor-error',
        dataScriptId: 'proveedores-data',
        formId: 'form-ingreso-carga',
        valor: function (proveedor) { return proveedor.identificador; },
        etiqueta: function (proveedor) {
            return proveedor.identificador + ' — ' + proveedor.nombre;
        },
    });

    window.AduaneroPicker = crearBuscadorSelector({
        pickerId: 'aduanero-picker',
        inputId: 'aduanero-buscar',
        hiddenId: 'aduanero-id-hidden',
        resultadosId: 'aduanero-resultados',
        dataScriptId: 'aduaneros-data',
        valor: function (aduanero) { return aduanero.identificador; },
        etiqueta: function (aduanero) {
            return aduanero.identificador + ' — ' + aduanero.nombre;
        },
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var openBtn = document.getElementById('btn-nuevo-cliente');
    var modal = document.getElementById('modal-nuevo-cliente');
    var closeBtn = document.getElementById('btn-cerrar-modal-cliente');

    if (!openBtn || !modal) {
        return;
    }

    openBtn.addEventListener('click', function () {
        modal.classList.remove('hidden');
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            modal.classList.add('hidden');
        });
    }
});

document.addEventListener('DOMContentLoaded', function () {
    var openBtn = document.getElementById('btn-nuevo-ingreso');
    var cancelBtn = document.getElementById('btn-cancelar-ingreso');
    var historialSection = document.getElementById('seccion-historial-cargas');
    var formSection = document.getElementById('seccion-form-ingreso');

    if (!historialSection || !formSection) {
        return;
    }

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            historialSection.classList.add('hidden');
            formSection.classList.remove('hidden');
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            formSection.classList.add('hidden');
            historialSection.classList.remove('hidden');
        });
    }
});

document.addEventListener('DOMContentLoaded', function () {
    var scanInput = document.getElementById('lpn-scan-input');
    var ubicacionSelect = document.getElementById('lpn-ubicacion-select');
    var mensaje = document.getElementById('lpn-scan-mensaje');
    var csrfInput = document.getElementById('lpn-csrf');
    var cargaIdInput = document.getElementById('lpn-carga-id');

    if (!scanInput || !ubicacionSelect || !csrfInput || !cargaIdInput) {
        return;
    }

    scanInput.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        var codigo = scanInput.value.trim();

        if (codigo === '') {
            return;
        }

        var body = new URLSearchParams();
        body.set('_csrf', csrfInput.value);
        body.set('codigo', codigo);
        body.set('ubicacion_id', ubicacionSelect.value);

        fetch('/cargas/' + cargaIdInput.value + '/recepcion/confirmar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    mensaje.textContent = data.message;
                    mensaje.className = 'text-sm text-red-600';
                    return;
                }

                var fila = document.querySelector('#lpn-tbody tr[data-codigo="' + data.lpn.codigo + '"]');

                if (fila) {
                    fila.querySelector('.lpn-estado-cell').innerHTML =
                        '<span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">' + data.lpn.estadoLabel + '</span>';
                    fila.querySelector('.lpn-ubicacion-cell').textContent =
                        ubicacionSelect.options[ubicacionSelect.selectedIndex].text;
                }

                mensaje.textContent = 'LPN ' + data.lpn.codigo + ' confirmado.';
                mensaje.className = 'text-sm text-green-600';
            })
            .catch(function () {
                mensaje.textContent = 'Error de conexion, intenta de nuevo.';
                mensaje.className = 'text-sm text-red-600';
            });

        scanInput.value = '';
        scanInput.focus();
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var scanInput = document.getElementById('salida-scan-input');
    var mensaje = document.getElementById('salida-scan-mensaje');
    var csrfInput = document.getElementById('salida-csrf');
    var ordenIdInput = document.getElementById('salida-orden-id');
    var progresoEl = document.getElementById('salida-progreso');
    var confirmarBtn = document.getElementById('salida-confirmar-btn');

    if (!scanInput || !csrfInput || !ordenIdInput) {
        return;
    }

    scanInput.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        var codigo = scanInput.value.trim();

        if (codigo === '') {
            return;
        }

        var body = new URLSearchParams();
        body.set('_csrf', csrfInput.value);
        body.set('codigo', codigo);

        fetch('/salidas/' + ordenIdInput.value + '/escanear', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) {
                    mensaje.textContent = data.message;
                    mensaje.className = 'text-sm text-red-600';
                    return;
                }

                var fila = document.querySelector('#salida-tbody tr[data-codigo="' + data.codigo + '"]');

                if (fila) {
                    fila.querySelector('.salida-linea-estado').innerHTML =
                        '<span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Escaneado</span>';
                }

                if (progresoEl) {
                    progresoEl.textContent = data.progreso.escaneadas + ' de ' + data.progreso.total + ' escaneados';
                    progresoEl.setAttribute('data-escaneadas', data.progreso.escaneadas);
                }

                if (confirmarBtn) {
                    confirmarBtn.disabled = data.progreso.escaneadas < data.progreso.total;
                }

                mensaje.textContent = 'LPN ' + data.codigo + ' escaneado.';
                mensaje.className = 'text-sm text-green-600';
            })
            .catch(function () {
                mensaje.textContent = 'Error de conexion, intenta de nuevo.';
                mensaje.className = 'text-sm text-red-600';
            });

        scanInput.value = '';
        scanInput.focus();
    });
});

function initBusquedaPaginada(opciones) {
    var input = document.getElementById(opciones.inputId);
    var perPageSelect = document.getElementById(opciones.perPageId);
    var tbody = document.getElementById(opciones.tbodyId);
    var paginacion = document.getElementById(opciones.paginacionId);
    var filtroSelect = opciones.filtroId ? document.getElementById(opciones.filtroId) : null;

    if (!input || !tbody || !perPageSelect || !paginacion) {
        return;
    }

    function cargar(page) {
        var params = new URLSearchParams();
        params.set('q', input.value);
        params.set('page', String(page));
        params.set('per_page', perPageSelect.value);

        if (filtroSelect) {
            params.set(opciones.filtroParam || 'filtro', filtroSelect.value);
        }

        fetch(opciones.url + '?' + params.toString())
            .then(function (response) { return response.json(); })
            .then(function (data) {
                tbody.innerHTML = data.html;
                paginacion.innerHTML = data.paginacion;
            });
    }

    paginacion.addEventListener('click', function (event) {
        var btn = event.target.closest(opciones.botonClase);

        if (!btn || btn.disabled) {
            return;
        }

        cargar(parseInt(btn.getAttribute('data-page'), 10));
    });

    perPageSelect.addEventListener('change', function () {
        cargar(1);
    });

    if (filtroSelect) {
        filtroSelect.addEventListener('change', function () {
            cargar(1);
        });
    }

    var timer = null;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            cargar(1);
        }, 300);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initBusquedaPaginada({
        inputId: 'buscar-clientes',
        perPageId: 'clientes-per-page',
        tbodyId: 'clientes-tbody',
        paginacionId: 'clientes-paginacion',
        botonClase: '.clientes-pagina-btn',
        url: '/clientes/buscar'
    });

    initBusquedaPaginada({
        inputId: 'buscar-aduaneros',
        perPageId: 'aduaneros-per-page',
        tbodyId: 'aduaneros-tbody',
        paginacionId: 'aduaneros-paginacion',
        botonClase: '.aduaneros-pagina-btn',
        url: '/aduaneros/buscar'
    });

    initBusquedaPaginada({
        inputId: 'buscar-cargas',
        perPageId: 'cargas-per-page',
        tbodyId: 'cargas-tbody',
        paginacionId: 'cargas-paginacion',
        botonClase: '.cargas-pagina-btn',
        filtroId: 'cargas-estado-filtro',
        filtroParam: 'estado',
        url: '/cargas/buscar'
    });

    initBusquedaPaginada({
        inputId: 'buscar-proveedores',
        perPageId: 'proveedores-per-page',
        tbodyId: 'proveedores-tbody',
        paginacionId: 'proveedores-paginacion',
        botonClase: '.proveedores-pagina-btn',
        url: '/proveedores/buscar'
    });

    initBusquedaPaginada({
        inputId: 'buscar-paises',
        perPageId: 'paises-per-page',
        tbodyId: 'paises-tbody',
        paginacionId: 'paises-paginacion',
        botonClase: '.paises-pagina-btn',
        url: '/paises/buscar'
    });

    initBusquedaPaginada({
        inputId: 'buscar-usuarios',
        perPageId: 'usuarios-per-page',
        tbodyId: 'usuarios-tbody',
        paginacionId: 'usuarios-paginacion',
        botonClase: '.usuarios-pagina-btn',
        url: '/usuarios/buscar'
    });

    initBusquedaPaginada({
        inputId: 'buscar-ubicaciones',
        perPageId: 'ubicaciones-per-page',
        tbodyId: 'ubicaciones-tbody',
        paginacionId: 'ubicaciones-paginacion',
        botonClase: '.ubicaciones-pagina-btn',
        url: '/ubicaciones/buscar'
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.getElementById('factura-lineas-tbody');
    var addBtn = document.getElementById('btn-agregar-linea');

    if (!tbody || !addBtn) {
        return;
    }

    function bindQuitar(row) {
        var quitarBtn = row.querySelector('.factura-linea-quitar');

        if (!quitarBtn) {
            return;
        }

        quitarBtn.addEventListener('click', function () {
            if (tbody.querySelectorAll('.factura-linea-row').length > 1) {
                row.remove();
            }
        });
    }

    tbody.querySelectorAll('.factura-linea-row').forEach(bindQuitar);

    addBtn.addEventListener('click', function () {
        var rows = tbody.querySelectorAll('.factura-linea-row');
        var nuevaFila = rows[rows.length - 1].cloneNode(true);

        nuevaFila.querySelectorAll('input').forEach(function (input) {
            input.value = input.name === 'cantidad[]' ? '1' : '';
        });

        tbody.appendChild(nuevaFila);
        bindQuitar(nuevaFila);
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.getElementById('carga-lineas-tbody');
    var addBtn = document.getElementById('btn-agregar-linea-carga');

    if (!tbody || !addBtn) {
        return;
    }

    function recalcularCubicaje(row) {
        if (row.dataset.cubicajeManual === 'true') {
            return;
        }

        var ancho = parseFloat(row.querySelector('.carga-linea-ancho').value) || 0;
        var alto = parseFloat(row.querySelector('.carga-linea-alto').value) || 0;
        var largo = parseFloat(row.querySelector('.carga-linea-largo').value) || 0;
        var cubicaje = (ancho * alto * largo) / 28316.846592;

        row.querySelector('.carga-linea-cubicaje').value = cubicaje.toFixed(6);
    }

    function bindFila(row) {
        var quitarBtn = row.querySelector('.carga-linea-quitar');

        if (quitarBtn) {
            quitarBtn.addEventListener('click', function () {
                if (tbody.querySelectorAll('.carga-linea-row').length > 1) {
                    row.remove();
                }
            });
        }

        ['.carga-linea-ancho', '.carga-linea-alto', '.carga-linea-largo'].forEach(function (selector) {
            row.querySelector(selector).addEventListener('input', function () {
                recalcularCubicaje(row);
            });
        });

        row.querySelector('.carga-linea-cubicaje').addEventListener('input', function () {
            row.dataset.cubicajeManual = 'true';
        });
    }

    tbody.querySelectorAll('.carga-linea-row').forEach(bindFila);

    addBtn.addEventListener('click', function () {
        var rows = tbody.querySelectorAll('.carga-linea-row');
        var nuevaFila = rows[rows.length - 1].cloneNode(true);

        nuevaFila.querySelectorAll('input').forEach(function (input) {
            if (input.classList.contains('carga-linea-cubicaje')) {
                input.value = '0.000000';
            } else if (input.name === 'linea_cantidad[]') {
                input.value = '1';
            } else {
                input.value = '';
            }
        });

        delete nuevaFila.dataset.cubicajeManual;

        var nuevoQuitarBtn = nuevaFila.querySelector('.carga-linea-quitar');

        if (nuevoQuitarBtn) {
            nuevoQuitarBtn.classList.remove('hidden');
        }

        tbody.appendChild(nuevaFila);
        bindFila(nuevaFila);
    });
});
