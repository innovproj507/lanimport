<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Redirige la raiz al login (mismo comportamiento que /lan)
$routes->get('/', static function () {
    return redirect()->to('/login');
});

// Auth (sin filtro — son las rutas para autenticarse)
$routes->get('/login', 'AuthController::showLogin');
$routes->post('/login', 'AuthController::login');
$routes->post('/logout', 'AuthController::logout');
$routes->get('/forgot-password', 'AuthController::showForgotPasswordForm');
$routes->post('/forgot-password', 'AuthController::forgotPassword');
$routes->get('/reset-password/(:segment)', 'AuthController::showResetPasswordForm/$1');
$routes->post('/reset-password/(:segment)', 'AuthController::resetPassword/$1');

// Todos los roles con sesion salvo 'recepcion', que solo usa Recepcion de Carga.
$sinRecepcion = ['auth', 'role:cliente,aduanero,operaciones,bodega,gerente,admin'];

// Requieren sesion activa (DashboardController manda al rol recepcion a /recepciones)
$routes->get('/dashboard', 'DashboardController::index', ['filter' => 'auth']);

// Portal de cliente — requiere sesion + rol cliente
$routes->get('/portal', 'PortalController::index', ['filter' => ['auth', 'role:cliente']]);

// Tracking — requiere sesion (cualquier rol, salvo recepcion)
$routes->get('/tracking', 'TrackingController::showLookupForm', ['filter' => $sinRecepcion]);
$routes->post('/tracking/buscar', 'TrackingController::lookup', ['filter' => $sinRecepcion]);

// Paises — requiere sesion + rol gerente/admin (igual que $soloGerente en WebRoutes.php)
$routes->group('paises', ['filter' => ['auth', 'role:gerente,admin']], static function ($routes) {
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
    $routes->post('(:segment)/eliminar', 'ClienteController::delete/$1');
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
$routes->get('cargas/(:segment)', 'CargaController::showDetalle/$1', ['filter' => $sinRecepcion]);
$routes->get('cargas/(:segment)/editar', 'CargaController::showEditForm/$1', ['filter' => $bodegaFilter]);
$routes->post('cargas/(:segment)/editar', 'CargaController::update/$1', ['filter' => $bodegaFilter]);
$routes->post('cargas/(:segment)/reenviar-correo', 'CargaController::reenviarCorreo/$1', ['filter' => ['auth', 'role:operaciones,gerente,admin']]);
$routes->post('cargas/(:segment)/lpns/generar', 'CargaController::generarLpns/$1', ['filter' => $bodegaFilter]);
$routes->post('cargas/(:segment)/lpns/eliminar', 'CargaController::eliminarLpns/$1', ['filter' => ['auth', 'role:gerente,admin']]);
$routes->post('lpn/plantilla-etiqueta', 'LpnController::guardarPlantillaEtiqueta', ['filter' => ['auth', 'role:gerente,admin']]);
// Actas de recepcion: las ve el personal interno; las llenan bodega/admin (con o sin carga
// registrada) y las corrigen bodega/gerente/admin (la regla fina esta en ActaRecepcion).
$recepcionFilter = ['auth', 'role:recepcion,operaciones,bodega,gerente,admin'];
$routes->get('recepciones', 'ActaRecepcionController::index', ['filter' => $recepcionFilter]);
$routes->get('recepciones/nueva', 'ActaRecepcionController::nueva', ['filter' => ['auth', 'role:recepcion,bodega,admin']]);
$routes->post('recepciones', 'ActaRecepcionController::crear', ['filter' => ['auth', 'role:recepcion,bodega,admin']]);
$routes->get('recepciones/(:segment)', 'ActaRecepcionController::show/$1', ['filter' => $recepcionFilter]);
$routes->post('recepciones/(:segment)', 'ActaRecepcionController::corregir/$1', ['filter' => ['auth', 'role:recepcion,bodega,gerente,admin']]);
$routes->get('recepciones/(:segment)/imprimir', 'ActaRecepcionController::imprimir/$1', ['filter' => $recepcionFilter]);
$routes->post('recepciones/(:segment)/vincular', 'ActaRecepcionController::vincular/$1', ['filter' => $bodegaFilter]);
$routes->get('cargas/(:segment)/acta-recepcion', 'ActaRecepcionController::porCarga/$1', ['filter' => $recepcionFilter]);
$routes->get('cargas/(:segment)/recepcion','LpnController::showRecepcion/$1', ['filter' => $bodegaFilter]);
$routes->post('cargas/(:segment)/recepcion/confirmar', 'LpnController::confirmarRecepcion/$1', ['filter' => $bodegaFilter]);

// Inventario — requiere sesion + rol operaciones/bodega/gerente/admin
$routes->get('inventario', 'InventarioController::index', ['filter' => $bodegaFilter]);

// Salidas — requiere sesion + rol operaciones/bodega/gerente/admin (igual que $bodegaRoles en WebRoutes.php)
$routes->get('salidas/historial', 'SalidaController::historial', ['filter' => $bodegaFilter]);
$routes->get('salidas', 'SalidaController::index', ['filter' => $bodegaFilter]);
$routes->post('salidas', 'SalidaController::store', ['filter' => $bodegaFilter]);
$routes->get('salidas/(:segment)', 'SalidaController::show/$1', ['filter' => $bodegaFilter]);
$routes->post('salidas/(:segment)/escanear', 'SalidaController::confirmarEscaneo/$1', ['filter' => $bodegaFilter]);
$routes->post('salidas/(:segment)/finalizar', 'SalidaController::finalizar/$1', ['filter' => $bodegaFilter]);
$routes->post('salidas/(:segment)/cancelar', 'SalidaController::cancelar/$1', ['filter' => $bodegaFilter]);

// Reempaques — requiere sesion + rol operaciones/bodega/gerente/admin (igual que $bodegaRoles en WebRoutes.php)
$routes->get('reempaques/historial', 'ReempaqueController::historial', ['filter' => $bodegaFilter]);
$routes->get('reempaques', 'ReempaqueController::index', ['filter' => $bodegaFilter]);
$routes->post('reempaques', 'ReempaqueController::store', ['filter' => $bodegaFilter]);
$routes->get('reempaques/(:segment)', 'ReempaqueController::show/$1', ['filter' => $bodegaFilter]);
$routes->post('reempaques/(:segment)/completar', 'ReempaqueController::completar/$1', ['filter' => $bodegaFilter]);
$routes->post('reempaques/(:segment)/cancelar', 'ReempaqueController::cancelar/$1', ['filter' => $bodegaFilter]);

// Impresoras — requiere sesion + rol operaciones/bodega/gerente/admin
$routes->get('impresoras', 'ImpresoraController::index', ['filter' => $bodegaFilter]);

// Usuarios — requiere sesion + rol gerente/admin (igual que $soloGerente en WebRoutes.php)
$routes->group('usuarios', ['filter' => ['auth', 'role:gerente,admin']], static function ($routes) {
    $routes->get('/', 'UsuarioController::index');
    $routes->get('buscar', 'UsuarioController::buscar');
    $routes->get('nuevo', 'UsuarioController::showCreateForm');
    $routes->post('/', 'UsuarioController::store');
    $routes->get('(:segment)/editar', 'UsuarioController::showEditForm/$1');
    $routes->post('(:segment)/editar', 'UsuarioController::update/$1');
    $routes->post('(:segment)/activar', 'UsuarioController::activar/$1');
    $routes->post('(:segment)/desactivar', 'UsuarioController::desactivar/$1');
});

// Configuracion del sistema — requiere sesion + rol gerente/admin (igual que Usuarios/Paises).
// Impresoras queda fuera del grupo: es por estacion y la usa tambien bodega.
$routes->group('configuracion', ['filter' => ['auth', 'role:gerente,admin']], static function ($routes) {
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

// Reportes —requiere sesion + rol operaciones/gerente/admin (igual que $gestionRoles en WebRoutes.php)
$routes->get('reportes', 'ReporteController::index', ['filter' => ['auth', 'role:operaciones,gerente,admin']]);

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

    $routes->get('lpn/(:segment)/genealogy', 'Api\GenealogiaApiController::show/$1', [
        'filter' => ['jwt', 'apirole:operaciones,bodega,gerente,admin'],
    ]);
});
