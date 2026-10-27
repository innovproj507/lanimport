<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Raiz (y inicio de la app instalada): a la pantalla del rol si hay sesion, si no al login.
$routes->get('/', 'AuthController::inicio');

// Auth (sin filtro — son las rutas para autenticarse)
$routes->get('/login', 'AuthController::showLogin');
$routes->post('/login', 'AuthController::login');
$routes->post('/logout', 'AuthController::logout');
$routes->get('/forgot-password', 'AuthController::showForgotPasswordForm');
$routes->post('/forgot-password', 'AuthController::forgotPassword');
$routes->get('/reset-password/(:segment)', 'AuthController::showResetPasswordForm/$1');
$routes->post('/reset-password/(:segment)', 'AuthController::resetPassword/$1');

// Requieren sesion activa (DashboardController manda al rol bodega a /cargas/ingreso)
$routes->get('/dashboard', 'DashboardController::index', ['filter' => 'auth']);

// Actualizacion automatica de pantallas (vivo.js): huella de los datos, para refrescar solo si cambiaron.
$routes->get('/vivo/huella', 'VivoController::huella', ['filter' => 'auth']);
$routes->get('/vivo/campana', 'VivoController::campana', ['filter' => 'auth']);

// Portal de cliente — requiere sesion + rol cliente
$routes->get('/portal', 'PortalController::index', ['filter' => ['auth', 'role:cliente']]);

// Tracking — requiere sesion (cualquier rol, salvo bodega)
$rastreoFilter = ['auth', 'role:cliente,aduanero,operaciones,gerente,admin'];
$routes->get('/tracking', 'TrackingController::showLookupForm', ['filter' => $rastreoFilter]);
$routes->post('/tracking/buscar', 'TrackingController::lookup', ['filter' => $rastreoFilter]);

// Paises — solo Owner (rol admin): es parte de Configuracion
$routes->group('paises', ['filter' => ['auth', 'role:admin']], static function ($routes) {
    $routes->get('/', 'PaisController::index');
    $routes->get('buscar', 'PaisController::buscar');
    $routes->get('nuevo', 'PaisController::showCreateForm');
    $routes->post('/', 'PaisController::store');
    $routes->get('(:segment)/editar', 'PaisController::showEditForm/$1');
    $routes->post('(:segment)/editar', 'PaisController::update/$1');
});

// Aduaneros — requiere sesion + rol operaciones/gerente/admin (igual que $gestionRoles en WebRoutes.php)
$routes->group('aduaneros', ['filter' => ['auth', 'role:operaciones,gerente,admin']], static function ($routes) {
    $routes->get('/', 'AduaneroController::index');
    $routes->get('buscar', 'AduaneroController::buscar');
    $routes->get('nuevo', 'AduaneroController::showCreateForm');
    $routes->post('/', 'AduaneroController::store');
    $routes->get('(:segment)/editar', 'AduaneroController::showEditForm/$1');
    $routes->post('(:segment)/editar', 'AduaneroController::update/$1');
    $routes->post('(:segment)/desactivar', 'AduaneroController::desactivar/$1');
    $routes->post('(:segment)/activar', 'AduaneroController::activar/$1');
});

// Proveedores — requiere sesion + rol operaciones/gerente/admin (igual que $gestionRoles en WebRoutes.php)
$routes->group('proveedores', ['filter' => ['auth', 'role:operaciones,gerente,admin']], static function ($routes) {
    $routes->get('/', 'ProveedorController::index');
    $routes->get('buscar', 'ProveedorController::buscar');
    $routes->get('nuevo', 'ProveedorController::showCreateForm');
    $routes->post('/', 'ProveedorController::store');
    $routes->get('(:segment)/editar', 'ProveedorController::showEditForm/$1');
    $routes->post('(:segment)/editar', 'ProveedorController::update/$1');
});

// Navieras — catalogo para las salidas de contenedores; mismos roles que Proveedores
$routes->group('navieras', ['filter' => ['auth', 'role:operaciones,gerente,admin']], static function ($routes) {
    $routes->get('/', 'NavieraController::index');
    $routes->get('buscar', 'NavieraController::buscar');
    $routes->get('nuevo', 'NavieraController::showCreateForm');
    $routes->post('/', 'NavieraController::store');
    $routes->get('(:segment)/editar', 'NavieraController::showEditForm/$1');
    $routes->post('(:segment)/editar', 'NavieraController::update/$1');
    $routes->post('(:segment)/desactivar', 'NavieraController::desactivar/$1');
    $routes->post('(:segment)/activar', 'NavieraController::activar/$1');
});

// Eliminar clientes/aduaneros/navieras: solo gerencia y owner, y solo si nunca se usaron
// (si tienen historial el caso de uso lo rechaza y se ofrece desactivar). Fuera de los grupos
// para que aplique este filtro de rol y no el de cada grupo.
$eliminarFilter = ['auth', 'role:gerente,admin'];
$routes->post('clientes/(:segment)/eliminar', 'ClienteController::delete/$1', ['filter' => $eliminarFilter]);
$routes->post('aduaneros/(:segment)/eliminar', 'AduaneroController::eliminar/$1', ['filter' => $eliminarFilter]);
$routes->post('navieras/(:segment)/eliminar', 'NavieraController::eliminar/$1', ['filter' => $eliminarFilter]);

// rapido de Cliente/Proveedor se usan desde el picker de Ingreso de Carga — requieren
// $bodegaRoles (incluye bodega), no $gestionRoles, igual que WebRoutes.php linea 62-63.
$routes->post('clientes/rapido', 'ClienteController::storeRapido', ['filter' => ['auth', 'role:operaciones,bodega,gerente,admin']]);
$routes->post('proveedores/rapido', 'ProveedorController::storeRapido', ['filter' => ['auth', 'role:operaciones,bodega,gerente,admin']]);

// Ubicaciones — requiere sesion + rol operaciones/gerente/admin (igual que $gestionRoles en WebRoutes.php)
$routes->group('ubicaciones', ['filter' => ['auth', 'role:operaciones,gerente,admin']], static function ($routes) {
    $routes->get('/', 'UbicacionController::index');
    $routes->get('buscar', 'UbicacionController::buscar');
    $routes->get('nuevo', 'UbicacionController::showCreateForm');
    $routes->post('/', 'UbicacionController::store');
    $routes->get('(:segment)/editar', 'UbicacionController::showEditForm/$1');
    $routes->post('(:segment)/editar', 'UbicacionController::update/$1');
    $routes->post('(:segment)/activar', 'UbicacionController::activar/$1');
    $routes->post('(:segment)/desactivar', 'UbicacionController::desactivar/$1');
});

// Clientes — requiere sesion + rol operaciones/gerente/admin (igual que $gestionRoles en WebRoutes.php)
$routes->group('clientes', ['filter' => ['auth', 'role:operaciones,gerente,admin']], static function ($routes) {
    $routes->get('/', 'ClienteController::index');
    $routes->get('buscar', 'ClienteController::buscar');
    $routes->get('nuevo', 'ClienteController::showCreateForm');
    $routes->post('/', 'ClienteController::store');
    $routes->get('(:segment)', 'ClienteController::show/$1');
    $routes->get('(:segment)/editar', 'ClienteController::showEditForm/$1');
    $routes->post('(:segment)/editar', 'ClienteController::update/$1');
    $routes->post('(:segment)/desactivar', 'ClienteController::desactivar/$1');
    $routes->post('(:segment)/activar', 'ClienteController::activar/$1');
    $routes->post('(:segment)/aprobar', 'ClienteController::aprobar/$1');
    $routes->post('(:segment)/rechazar', 'ClienteController::rechazar/$1');
});

// Cargas — roles mixtos por ruta, igual que WebRoutes.php (bodegaRoles = operaciones/bodega/gerente/admin,
// salvo detalle que solo exige sesion, y lpns/eliminar que exige gerente/admin).
$bodegaFilter = ['auth', 'role:operaciones,bodega,gerente,admin'];
$routes->get('cargas', 'CargaController::redirectToIngreso', ['filter' => $bodegaFilter]);
$routes->get('cargas/ingreso', 'CargaController::showIngresoForm', ['filter' => $bodegaFilter]);
$routes->post('cargas', 'CargaController::store', ['filter' => $bodegaFilter]);
$routes->get('cargas/buscar', 'CargaController::buscar', ['filter' => $bodegaFilter]);
// Carga masiva por Excel/CSV — solo gerencia y owner. Va antes de cargas/(:segment).
$masivaFilter = ['auth', 'role:gerente,admin'];
$routes->get('cargas/masiva', 'CargaMasivaController::index', ['filter' => $masivaFilter]);
$routes->get('cargas/masiva/plantilla', 'CargaMasivaController::plantilla', ['filter' => $masivaFilter]);
$routes->post('cargas/masiva/previsualizar', 'CargaMasivaController::previsualizar', ['filter' => $masivaFilter]);
$routes->post('cargas/masiva/confirmar', 'CargaMasivaController::confirmar', ['filter' => $masivaFilter]);
$routes->get('cargas/(:segment)', 'CargaController::showDetalle/$1', ['filter' => 'auth']);
$routes->get('cargas/(:segment)/editar', 'CargaController::showEditForm/$1', ['filter' => $bodegaFilter]);
$routes->post('cargas/(:segment)/editar', 'CargaController::update/$1', ['filter' => $bodegaFilter]);
$routes->post('cargas/(:segment)/reenviar-correo', 'CargaController::reenviarCorreo/$1', ['filter' => ['auth', 'role:operaciones,gerente,admin']]);
// Aviso al cliente por WhatsApp (abre wa.me con el mensaje listo): ingreso o despacho.
$routes->post('cargas/(:segment)/whatsapp/(ingreso|despacho)', 'CargaController::avisarWhatsapp/$1/$2', ['filter' => ['auth', 'role:operaciones,gerente,admin']]);
$routes->post('cargas/(:segment)/lpns/generar', 'CargaController::generarLpns/$1', ['filter' => $bodegaFilter]);
$routes->post('cargas/(:segment)/lpns/eliminar', 'CargaController::eliminarLpns/$1', ['filter' => ['auth', 'role:gerente,admin']]);
// Fotos de evidencia (se suben por bodega/api/.../fotos): borrarlas, solo gerencia y owner.
$routes->post('fotos-evidencia/(:segment)/eliminar', 'FotoEvidenciaController::eliminar/$1', ['filter' => ['auth', 'role:gerente,admin']]);
$routes->post('lpn/plantilla-etiqueta', 'LpnController::guardarPlantillaEtiqueta', ['filter' => ['auth', 'role:admin']]);
// Comprobante de recepcion imprimible (datos de entrega + espacios para firmas).
$routes->get('cargas/(:segment)/comprobante', 'CargaController::comprobante/$1', ['filter' => $bodegaFilter]);
$routes->get('cargas/(:segment)/recepcion','LpnController::showRecepcion/$1', ['filter' => $bodegaFilter]);
$routes->post('cargas/(:segment)/recepcion/confirmar', 'LpnController::confirmarRecepcion/$1', ['filter' => $bodegaFilter]);
// App movil de bodega (PDA/tablet): pagina unica + API JSON con la misma sesion.
// Los POST llevan el token CSRF en la cabecera X-CSRF-Token (filtro csrfheader).
$routes->get('bodega/sw.js', 'BodegaAppController::serviceWorker');
$routes->get('bodega', 'BodegaAppController::index', ['filter' => $bodegaFilter]);
$routes->group('bodega/api', ['filter' => array_merge($bodegaFilter, ['csrfheader'])], static function ($routes) {
    $routes->get('ubicaciones', 'Api\BodegaApiController::ubicaciones');
    $routes->get('recepcion', 'Api\BodegaApiController::recepcionPendientes');
    $routes->post('recepcion/escanear', 'Api\BodegaApiController::recepcionEscanear');
    // Bultos de una carga y cuales faltan por escanear.
    $routes->get('recepcion/(:segment)/bultos', 'Api\BodegaApiController::recepcionBultos/$1');
    $routes->get('lpns/(:segment)', 'Api\BodegaApiController::lpn/$1');
    $routes->get('salidas', 'Api\BodegaApiController::salidas');
    $routes->get('salidas/(:segment)', 'Api\BodegaApiController::salida/$1');
    $routes->post('salidas/(:segment)/escanear', 'Api\BodegaApiController::salidaEscanear/$1');
    $routes->post('salidas/(:segment)/finalizar', 'Api\BodegaApiController::salidaFinalizar/$1');
    // Fotos de evidencia: de recepcion (por carga) y de salida (por orden). Tambien las usa la web.
    $routes->get('(cargas|salidas)/(:segment)/fotos', 'Api\FotoEvidenciaApiController::listar/$1/$2');
    $routes->post('(cargas|salidas)/(:segment)/fotos', 'Api\FotoEvidenciaApiController::subir/$1/$2');
    // Reempaque: bodega ejecuta la orden de trafico caja por caja.
    $routes->get('reempaques', 'Api\BodegaApiController::reempaques');
    $routes->get('reempaques/(:segment)', 'Api\BodegaApiController::reempaque/$1');
    $routes->post('reempaques/(:segment)/(abrir-caja|empacar|quitar|cerrar-caja|descartar-caja|terminar)', 'Api\BodegaApiController::reempaqueAccion/$1/$2');
});
// Recepcion general de bodega: escanear las etiquetas de cualquier carga (LPN "En recepcion" -> "Disponible").
$routes->get('recepcion', 'LpnController::recepcionGeneral', ['filter' => $bodegaFilter]);
$routes->post('recepcion/escanear', 'LpnController::escanearRecepcion', ['filter' => $bodegaFilter]);

// Inventario — requiere sesion + rol operaciones/bodega/gerente/admin
$routes->get('inventario', 'InventarioController::index', ['filter' => $bodegaFilter]);

// Salidas — bodega ve, escanea, finaliza y toma fotos; armar, agregar cargas, cancelar y el embarque
// los decide trafico/gerencia/owner (bodega no toma decisiones).
$planificaSalida = ['auth', 'role:operaciones,gerente,admin'];
$routes->get('salidas/historial', 'SalidaController::historial', ['filter' => $bodegaFilter]);
$routes->get('salidas', 'SalidaController::index', ['filter' => $planificaSalida]);
$routes->post('salidas', 'SalidaController::store', ['filter' => $planificaSalida]);
$routes->get('salidas/(:segment)', 'SalidaController::show/$1', ['filter' => $bodegaFilter]);
$routes->post('salidas/(:segment)/escanear', 'SalidaController::confirmarEscaneo/$1', ['filter' => $bodegaFilter]);
$routes->post('salidas/(:segment)/finalizar', 'SalidaController::finalizar/$1', ['filter' => $bodegaFilter]);
$routes->post('salidas/(:segment)/cancelar', 'SalidaController::cancelar/$1', ['filter' => $planificaSalida]);
// Bultos listos de una carga, para "Elegir etiquetas" al armar el contenedor (va antes de salidas/(:segment)).
$routes->get('salidas/lpns-listos/(:segment)', 'SalidaController::lpnsListos/$1', ['filter' => $planificaSalida]);
$routes->get('salidas/(:segment)/agregar', 'SalidaController::agregarForm/$1', ['filter' => $planificaSalida]);
$routes->post('salidas/(:segment)/agregar', 'SalidaController::agregar/$1', ['filter' => $planificaSalida]);
// Unir este DO a otro del mismo contenedor, pasar una carga a otro DO o devolverla a bodega.
$routes->post('salidas/(:segment)/unir', 'SalidaController::unir/$1', ['filter' => $planificaSalida]);
$routes->post('salidas/(:segment)/mover-carga', 'SalidaController::moverCarga/$1', ['filter' => $planificaSalida]);
// Ajustar que bultos de una carga van en el DO (p.ej. 20 de 143): lista y guardar.
$routes->get('salidas/(:segment)/carga-bultos/(:segment)', 'SalidaController::bultosCarga/$1/$2', ['filter' => $planificaSalida]);
$routes->post('salidas/(:segment)/ajustar-carga', 'SalidaController::ajustarCarga/$1', ['filter' => $planificaSalida]);
$routes->post('salidas/(:segment)/devolver-carga', 'SalidaController::devolverCarga/$1', ['filter' => $planificaSalida]);
$routes->post('salidas/(:segment)/embarque','SalidaController::guardarEmbarque/$1', ['filter' => $planificaSalida]);
$routes->get('salidas/(:segment)/manifiesto', 'SalidaController::manifiesto/$1', ['filter' => $bodegaFilter]);
$routes->get('salidas/(:segment)/orden-carga', 'SalidaController::ordenCarga/$1', ['filter' => $bodegaFilter]);
$routes->get('salidas/(:segment)/reporte', 'SalidaController::reporte/$1', ['filter' => $bodegaFilter]);
$routes->get('salidas/(:segment)/reporte/excel', 'SalidaController::reporteExcel/$1', ['filter' => $bodegaFilter]);

// Reempaques: trafico (y gerencia/owner) ordena y cancela; bodega ve las ordenes y las ejecuta
// caja por caja en la app de bodega (/bodega/api/reempaques/*).
$ordenaReempaque = ['auth', 'role:operaciones,gerente,admin'];
$routes->get('reempaques/historial', 'ReempaqueController::historial', ['filter' => $bodegaFilter]);
$routes->get('reempaques', 'ReempaqueController::index', ['filter' => $ordenaReempaque]);
$routes->post('reempaques', 'ReempaqueController::store', ['filter' => $ordenaReempaque]);
$routes->get('reempaques/(:segment)', 'ReempaqueController::show/$1', ['filter' => $bodegaFilter]);
$routes->post('reempaques/(:segment)/cancelar', 'ReempaqueController::cancelar/$1', ['filter' => $ordenaReempaque]);

// Impresoras — trafico/gerencia/owner (bodega no configura impresoras)
$routes->get('impresoras', 'ImpresoraController::index', ['filter' => ['auth', 'role:operaciones,gerente,admin']]);

// Usuarios — solo Owner (rol admin): es parte de Configuracion
$routes->group('usuarios', ['filter' => ['auth', 'role:admin']], static function ($routes) {
    $routes->get('/', 'UsuarioController::index');
    $routes->get('buscar', 'UsuarioController::buscar');
    $routes->get('nuevo', 'UsuarioController::showCreateForm');
    $routes->post('/', 'UsuarioController::store');
    $routes->get('(:segment)/editar', 'UsuarioController::showEditForm/$1');
    $routes->post('(:segment)/editar', 'UsuarioController::update/$1');
    $routes->post('(:segment)/activar', 'UsuarioController::activar/$1');
    $routes->post('(:segment)/desactivar', 'UsuarioController::desactivar/$1');
});

// Configuracion del sistema — solo Owner (rol admin).
// Impresoras queda fuera del grupo: es por estacion y la usa tambien bodega.
$routes->group('configuracion', ['filter' => ['auth', 'role:admin']], static function ($routes) {
    $routes->get('/', 'ConfiguracionController::index');
    $routes->get('correo', 'ConfiguracionController::showCorreo');
    $routes->post('correo', 'ConfiguracionController::guardarCorreo');
    $routes->post('correo/probar', 'ConfiguracionController::probarCorreo');
    $routes->get('empresa', 'ConfiguracionController::showEmpresa');
    $routes->post('empresa', 'ConfiguracionController::guardarEmpresa');
    $routes->get('notificaciones', 'ConfiguracionController::showNotificaciones');
    $routes->post('notificaciones', 'ConfiguracionController::guardarNotificaciones');
    $routes->get('etiqueta-lpn', 'ConfiguracionController::showEtiquetaLpn');
});

// Reportes — gerencia y owner (trafico no)
$routes->get('reportes', 'ReporteController::index', ['filter' => ['auth', 'role:gerente,admin']]);

// API (JWT, sin sesion de navegador) — igual que interfaces/Api/Router/ApiRoutes.php
$routes->group('api/v1', static function ($routes) {
    $routes->post('auth/login', 'Api\AuthApiController::login', ['filter' => 'ratelimit:auth_login,10,60']);
    $routes->post('auth/refresh', 'Api\AuthApiController::refresh', ['filter' => 'ratelimit:auth_refresh,20,60']);

    $routes->post('clientes/solicitudes', 'Api\ClienteSolicitudApiController::store', ['filter' => 'ratelimit:clientes_solicitudes,5,600']);

    $routes->post('cargas', 'Api\CargaApiController::store', [
        'filter' => ['ratelimit:cargas_store', 'jwt', 'apirole:operaciones,bodega,gerente,admin'],
    ]);
    $routes->get('cargas/(:segment)', 'Api\CargaApiController::show/$1', ['filter' => 'jwt']);
    $routes->patch('cargas/(:segment)/estado', 'Api\EstadoApiController::update/$1', [
        'filter' => ['jwt', 'apirole:operaciones,bodega,gerente,admin'],
    ]);

    $routes->get('tracking/contenedor/(:segment)', 'Api\TrackingApiController::byContenedor/$1', [
        'filter' => ['ratelimit:tracking_contenedor', 'jwt', 'apirole:aduanero'],
    ]);
    $routes->get('tracking/(:segment)', 'Api\TrackingApiController::byNumero/$1', [
        'filter' => ['ratelimit:tracking_numero', 'jwt'],
    ]);

    // Rastreo para la web publica (weblan): clave compartida X-Api-Key, sin sesion ni JWT.
    // Limite alto porque todas las consultas llegan desde el servidor de la web; la web
    // limita a su vez por visitante.
    $routes->get('publico/rastreo/(:segment)', 'Api\RastreoPublicoApiController::show/$1', [
        'filter' => 'ratelimit:rastreo_publico,300,60',
    ]);

    $routes->get('lpn/(:segment)/genealogy', 'Api\GenealogiaApiController::show/$1', [
        'filter' => ['jwt', 'apirole:operaciones,bodega,gerente,admin'],
    ]);
});
