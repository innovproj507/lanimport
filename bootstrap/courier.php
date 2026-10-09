<?php

declare(strict_types=1);

use Courier\Auth\Application\Login\LoginHandler;
use Courier\Auth\Application\Logout\LogoutHandler;
use Courier\Auth\Application\RestablecerPassword\RestablecerPasswordHandler;
use Courier\Auth\Application\SolicitarRecuperacionPassword\SolicitarRecuperacionPasswordHandler;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Auth\Infrastructure\Persistence\PasswordResetTokenRepository;
use Courier\Auth\Infrastructure\Persistence\PdoRefreshTokenRepository;
use Courier\Auth\Infrastructure\Persistence\PdoUsuarioRepository;
use Courier\Auth\Infrastructure\RateLimiting\LoginAttemptRepository;
use Courier\Auth\Infrastructure\Security\JwtService;
use Courier\Auth\Infrastructure\Security\PasswordHasher;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Aduanero\Application\ActualizarAduanero\ActualizarAduaneroHandler;
use Courier\Aduanero\Application\BuscarAduaneros\BuscarAduanerosHandler;
use Courier\Aduanero\Application\ListarAduaneros\ListarAduanerosHandler;
use Courier\Aduanero\Application\RegistrarAduanero\RegistrarAduaneroHandler;
use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Aduanero\Infrastructure\Persistence\PdoAduaneroRepository;
use Courier\Proveedor\Application\ActualizarProveedor\ActualizarProveedorHandler;
use Courier\Proveedor\Application\BuscarProveedores\BuscarProveedoresHandler;
use Courier\Proveedor\Application\ListarProveedores\ListarProveedoresHandler;
use Courier\Proveedor\Application\RegistrarProveedor\RegistrarProveedorHandler;
use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Proveedor\Infrastructure\Persistence\PdoProveedorRepository;
use Courier\Pais\Application\ActualizarPais\ActualizarPaisHandler;
use Courier\Pais\Application\BuscarPaises\BuscarPaisesHandler;
use Courier\Pais\Application\ListarPaises\ListarPaisesHandler;
use Courier\Pais\Application\RegistrarPais\RegistrarPaisHandler;
use Courier\Pais\Domain\PaisRepositoryInterface;
use Courier\Pais\Infrastructure\Persistence\PdoPaisRepository;
use Courier\Auth\Application\RegistrarUsuario\RegistrarUsuarioHandler;
use Courier\Auth\Application\ActualizarUsuario\ActualizarUsuarioHandler;
use Courier\Auth\Application\ActivarUsuario\ActivarUsuarioHandler;
use Courier\Auth\Application\DesactivarUsuario\DesactivarUsuarioHandler;
use Courier\Auth\Application\BuscarUsuarios\BuscarUsuariosHandler;
use Courier\Carga\Application\ActualizarDatosCarga\ActualizarDatosCargaHandler;
use Courier\Carga\Application\ActualizarEstadoCarga\ActualizarEstadoCargaHandler;
use Courier\Carga\Application\BuscarCargas\BuscarCargasHandler;
use Courier\Carga\Application\ConsultarTrackingPorContenedor\ConsultarTrackingPorContenedorHandler;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroHandler;
use Courier\Carga\Application\GuardarActaRecepcion\GuardarActaRecepcionHandler;
use Courier\Carga\Application\ListarCargas\ListarCargasHandler;
use Courier\Carga\Application\VincularActaRecepcion\VincularActaRecepcionHandler;
use Courier\Carga\Application\ListarCargasPorCliente\ListarCargasPorClienteHandler;
use Courier\Carga\Application\RegistrarCarga\RegistrarCargaHandler;
use Courier\Carga\Domain\ActaRecepcionRepositoryInterface;
use Courier\Carga\Domain\BarcodeGeneratorInterface;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\TrackingNumeroGeneratorInterface;
use Courier\Carga\Infrastructure\Barcode\PicqerBarcodeGeneratorService;
use Courier\Carga\Infrastructure\Generator\UuidTrackingNumeroGenerator;
use Courier\Carga\Infrastructure\Persistence\PdoActaRecepcionRepository;
use Courier\Carga\Infrastructure\Persistence\PdoCargaRepository;
use Courier\Carga\Infrastructure\Persistence\PdoEtiquetaRepository;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Carga\Infrastructure\Storage\CargaDocumentoStorage;
use Courier\Lpn\Application\ActualizarEtiquetaLpnConfig\ActualizarEtiquetaLpnConfigHandler;
use Courier\Lpn\Application\BuscarInventario\BuscarInventarioHandler;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnHandler;
use Courier\Lpn\Application\EliminarLpnsDeCarga\EliminarLpnsDeCargaHandler;
use Courier\Lpn\Application\GenerarLpnsParaCarga\GenerarLpnsParaCargaHandler;
use Courier\Lpn\Application\ListarLpnsPorCarga\ListarLpnsPorCargaHandler;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesHandler;
use Courier\Lpn\Application\RegistrarUbicacion\RegistrarUbicacionHandler;
use Courier\Lpn\Application\ActualizarUbicacion\ActualizarUbicacionHandler;
use Courier\Lpn\Application\ActivarUbicacion\ActivarUbicacionHandler;
use Courier\Lpn\Application\DesactivarUbicacion\DesactivarUbicacionHandler;
use Courier\Lpn\Application\BuscarUbicaciones\BuscarUbicacionesHandler;
use Courier\Lpn\Domain\EtiquetaLpnConfigRepositoryInterface;
use Courier\Lpn\Domain\LpnBarcodeGeneratorInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Lpn\Infrastructure\Barcode\LpnBarcodeGeneratorService;
use Courier\Lpn\Infrastructure\Persistence\PdoEtiquetaLpnConfigRepository;
use Courier\Lpn\Infrastructure\Persistence\PdoLpnRepository;
use Courier\Lpn\Infrastructure\Persistence\PdoUbicacionRepository;
use Courier\Cliente\Application\ActualizarCliente\ActualizarClienteHandler;
use Courier\Cliente\Application\AprobarCliente\AprobarClienteHandler;
use Courier\Cliente\Application\BuscarClientes\BuscarClientesHandler;
use Courier\Cliente\Application\DesactivarCliente\DesactivarClienteHandler;
use Courier\Cliente\Application\ListarClientes\ListarClientesHandler;
use Courier\Cliente\Application\ListarClientesPendientes\ListarClientesPendientesHandler;
use Courier\Cliente\Application\RechazarCliente\RechazarClienteHandler;
use Courier\Cliente\Application\RegistrarCliente\RegistrarClienteHandler;
use Courier\Cliente\Application\SolicitarRegistroCliente\SolicitarRegistroClienteHandler;
use Courier\Cliente\Domain\ClienteDocumentoRepositoryInterface;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Cliente\Infrastructure\Persistence\PdoClienteDocumentoRepository;
use Courier\Cliente\Infrastructure\Persistence\PdoClienteRepository;
use Courier\Cliente\Infrastructure\Storage\ClienteDocumentoStorage;
use Courier\Configuracion\Application\GuardarConfiguracionSmtp\GuardarConfiguracionSmtpHandler;
use Courier\Configuracion\Application\GuardarDatosEmpresa\GuardarDatosEmpresaHandler;
use Courier\Configuracion\Application\GuardarPreferenciasNotificacion\GuardarPreferenciasNotificacionHandler;
use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Configuracion\Domain\ProbadorSmtpInterface;
use Courier\Configuracion\Infrastructure\Persistence\PdoConfiguracionRepository;
use Courier\Configuracion\Infrastructure\Security\SecretCipher;
use Courier\Notificacion\Application\NotificarIngresoCarga\NotificarIngresoCargaHandler;
use Courier\Notificacion\Domain\EmailServiceInterface;
use Courier\Notificacion\Domain\NotificacionRepositoryInterface;
use Courier\Notificacion\Infrastructure\Email\PhpMailerEmailService;
use Courier\Notificacion\Infrastructure\Persistence\PdoNotificacionRepository;
use Courier\Factura\Application\AnularFactura\AnularFacturaHandler;
use Courier\Factura\Application\CrearFactura\CrearFacturaHandler;
use Courier\Factura\Application\ListarFacturas\ListarFacturasHandler;
use Courier\Factura\Application\MarcarFacturaPagada\MarcarFacturaPagadaHandler;
use Courier\Factura\Application\VerFactura\VerFacturaHandler;
use Courier\Factura\Domain\FacturaRepositoryInterface;
use Courier\Factura\Infrastructure\Persistence\PdoFacturaRepository;
use Courier\Reempaque\Application\CancelarReempaque\CancelarReempaqueHandler;
use Courier\Reempaque\Application\CompletarReempaque\CompletarReempaqueHandler;
use Courier\Reempaque\Application\ConsultarGenealogiaLpn\ConsultarGenealogiaLpnHandler;
use Courier\Reempaque\Application\IniciarReempaque\IniciarReempaqueHandler;
use Courier\Reempaque\Application\ListarReempaqueOrdenes\ListarReempaqueOrdenesHandler;
use Courier\Reempaque\Application\VerReempaqueOrden\VerReempaqueOrdenHandler;
use Courier\Reempaque\Domain\GenealogiaLpnRepositoryInterface;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Reempaque\Infrastructure\Persistence\PdoGenealogiaLpnRepository;
use Courier\Reempaque\Infrastructure\Persistence\PdoReempaqueOrdenRepository;
use Courier\Reporte\Application\ObtenerReporteOperativo\ObtenerReporteOperativoHandler;
use Courier\Reporte\Application\ObtenerResumenDashboard\ObtenerResumenDashboardHandler;
use Courier\Reporte\Infrastructure\Persistence\PdoReporteOperativoRepository;
use Courier\Reporte\Infrastructure\Persistence\PdoResumenDashboardRepository;
use Courier\Salida\Application\CancelarOrdenSalida\CancelarOrdenSalidaHandler;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaHandler;
use Courier\Salida\Application\CrearOrdenSalida\CrearOrdenSalidaHandler;
use Courier\Salida\Application\ListarOrdenesSalida\ListarOrdenesSalidaHandler;
use Courier\Salida\Application\RegistrarEscaneoSalida\RegistrarEscaneoSalidaHandler;
use Courier\Salida\Application\VerOrdenSalida\VerOrdenSalidaHandler;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Salida\Infrastructure\Persistence\PdoSalidaOrdenRepository;
use Courier\Shared\Infrastructure\Config;
use Courier\Shared\Infrastructure\Container;
use Courier\Shared\Infrastructure\Logging\LoggerFactory;
use Courier\Shared\Infrastructure\Persistence\ConnectionFactory;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use Monolog\Logger;

$basePath = dirname(__DIR__);

require_once $basePath . '/vendor/autoload.php';

Config::load($basePath);
date_default_timezone_set(Config::get('APP_TIMEZONE', 'UTC'));

$container = new Container();

$container->set(PDO::class, fn () => ConnectionFactory::getInstance());
$container->set(UnitOfWork::class, fn (Container $c) => new UnitOfWork($c->get(PDO::class)));
$container->set(Logger::class, fn () => LoggerFactory::getInstance($basePath));

// Auth
$container->set(UsuarioRepositoryInterface::class, fn (Container $c) => new PdoUsuarioRepository($c->get(PDO::class)));
$container->set(PasswordHasher::class, fn () => new PasswordHasher());
$container->set(JwtService::class, fn () => new JwtService());
$container->set(SessionManager::class, fn () => new SessionManager());
$container->set(LoginAttemptRepository::class, fn (Container $c) => new LoginAttemptRepository($c->get(PDO::class)));
$container->set(PdoRefreshTokenRepository::class, fn (Container $c) => new PdoRefreshTokenRepository($c->get(PDO::class)));
$container->set(LoginHandler::class, fn (Container $c) => new LoginHandler(
    $c->get(UsuarioRepositoryInterface::class),
    $c->get(PasswordHasher::class),
    $c->get(JwtService::class),
    $c->get(SessionManager::class),
    $c->get(LoginAttemptRepository::class),
    $c->get(PdoRefreshTokenRepository::class),
));
$container->set(LogoutHandler::class, fn (Container $c) => new LogoutHandler($c->get(SessionManager::class)));
$container->set(PasswordResetTokenRepository::class, fn (Container $c) => new PasswordResetTokenRepository($c->get(PDO::class)));
$container->set(SolicitarRecuperacionPasswordHandler::class, fn (Container $c) => new SolicitarRecuperacionPasswordHandler(
    $c->get(UsuarioRepositoryInterface::class),
    $c->get(PasswordResetTokenRepository::class),
    $c->get(EmailServiceInterface::class),
    $c->get(Logger::class),
));
$container->set(RestablecerPasswordHandler::class, fn (Container $c) => new RestablecerPasswordHandler(
    $c->get(UsuarioRepositoryInterface::class),
    $c->get(PasswordResetTokenRepository::class),
    $c->get(PasswordHasher::class),
));
$container->set(RegistrarUsuarioHandler::class, fn (Container $c) => new RegistrarUsuarioHandler(
    $c->get(UsuarioRepositoryInterface::class),
    $c->get(PasswordHasher::class),
));
$container->set(ActualizarUsuarioHandler::class, fn (Container $c) => new ActualizarUsuarioHandler(
    $c->get(UsuarioRepositoryInterface::class),
    $c->get(PasswordHasher::class),
));
$container->set(ActivarUsuarioHandler::class, fn (Container $c) => new ActivarUsuarioHandler($c->get(UsuarioRepositoryInterface::class)));
$container->set(DesactivarUsuarioHandler::class, fn (Container $c) => new DesactivarUsuarioHandler($c->get(UsuarioRepositoryInterface::class)));
$container->set(BuscarUsuariosHandler::class, fn (Container $c) => new BuscarUsuariosHandler($c->get(UsuarioRepositoryInterface::class)));

// Carga
$container->set(CargaRepositoryInterface::class, fn (Container $c) => new PdoCargaRepository($c->get(PDO::class)));
$container->set(PdoHistorialEstadoRepository::class, fn (Container $c) => new PdoHistorialEstadoRepository($c->get(PDO::class)));
$container->set(PdoEtiquetaRepository::class, fn (Container $c) => new PdoEtiquetaRepository($c->get(PDO::class)));
$container->set(TrackingNumeroGeneratorInterface::class, fn (Container $c) => new UuidTrackingNumeroGenerator($c->get(CargaRepositoryInterface::class)));
$container->set(BarcodeGeneratorInterface::class, fn () => new PicqerBarcodeGeneratorService(dirname(__DIR__) . '/public'));
$container->set(CargaDocumentoStorage::class, fn () => new CargaDocumentoStorage(dirname(__DIR__) . '/public'));

// Lpn
$container->set(LpnRepositoryInterface::class, fn (Container $c) => new PdoLpnRepository($c->get(PDO::class)));
$container->set(UbicacionRepositoryInterface::class, fn (Container $c) => new PdoUbicacionRepository($c->get(PDO::class)));
$container->set(LpnBarcodeGeneratorInterface::class, fn () => new LpnBarcodeGeneratorService(dirname(__DIR__) . '/public'));
$container->set(EtiquetaLpnConfigRepositoryInterface::class, fn (Container $c) => new PdoEtiquetaLpnConfigRepository($c->get(PDO::class)));

$container->set(GenerarLpnsParaCargaHandler::class, fn (Container $c) => new GenerarLpnsParaCargaHandler(
    $c->get(CargaRepositoryInterface::class),
    $c->get(LpnRepositoryInterface::class),
    $c->get(LpnBarcodeGeneratorInterface::class),
    $c->get(PdoEtiquetaRepository::class),
    $c->get(ClienteRepositoryInterface::class),
    $c->get(PaisRepositoryInterface::class),
    $c->get(EtiquetaLpnConfigRepositoryInterface::class),
    $c->get(UnitOfWork::class),
));
$container->set(ActualizarEtiquetaLpnConfigHandler::class, fn (Container $c) => new ActualizarEtiquetaLpnConfigHandler(
    $c->get(EtiquetaLpnConfigRepositoryInterface::class),
));
$container->set(EliminarLpnsDeCargaHandler::class, fn (Container $c) => new EliminarLpnsDeCargaHandler(
    $c->get(CargaRepositoryInterface::class),
    $c->get(LpnRepositoryInterface::class),
    $c->get(LpnBarcodeGeneratorInterface::class),
    $c->get(UnitOfWork::class),
));
$container->set(ConfirmarRecepcionLpnHandler::class, fn (Container $c) => new ConfirmarRecepcionLpnHandler(
    $c->get(LpnRepositoryInterface::class),
    $c->get(UbicacionRepositoryInterface::class),
));
$container->set(ListarLpnsPorCargaHandler::class, fn (Container $c) => new ListarLpnsPorCargaHandler($c->get(LpnRepositoryInterface::class)));
$container->set(BuscarInventarioHandler::class, fn (Container $c) => new BuscarInventarioHandler($c->get(LpnRepositoryInterface::class)));
$container->set(ListarUbicacionesHandler::class, fn (Container $c) => new ListarUbicacionesHandler($c->get(UbicacionRepositoryInterface::class)));
$container->set(RegistrarUbicacionHandler::class, fn (Container $c) => new RegistrarUbicacionHandler($c->get(UbicacionRepositoryInterface::class)));
$container->set(ActualizarUbicacionHandler::class, fn (Container $c) => new ActualizarUbicacionHandler($c->get(UbicacionRepositoryInterface::class)));
$container->set(ActivarUbicacionHandler::class, fn (Container $c) => new ActivarUbicacionHandler($c->get(UbicacionRepositoryInterface::class)));
$container->set(DesactivarUbicacionHandler::class, fn (Container $c) => new DesactivarUbicacionHandler($c->get(UbicacionRepositoryInterface::class)));
$container->set(BuscarUbicacionesHandler::class, fn (Container $c) => new BuscarUbicacionesHandler($c->get(UbicacionRepositoryInterface::class)));

// Salida
$container->set(SalidaOrdenRepositoryInterface::class, fn (Container $c) => new PdoSalidaOrdenRepository($c->get(PDO::class)));

$container->set(CrearOrdenSalidaHandler::class, fn (Container $c) => new CrearOrdenSalidaHandler(
    $c->get(LpnRepositoryInterface::class),
    $c->get(SalidaOrdenRepositoryInterface::class),
    $c->get(UnitOfWork::class),
));
$container->set(RegistrarEscaneoSalidaHandler::class, fn (Container $c) => new RegistrarEscaneoSalidaHandler($c->get(SalidaOrdenRepositoryInterface::class)));
$container->set(ConfirmarSalidaHandler::class, fn (Container $c) => new ConfirmarSalidaHandler(
    $c->get(SalidaOrdenRepositoryInterface::class),
    $c->get(LpnRepositoryInterface::class),
    $c->get(UnitOfWork::class),
));
$container->set(CancelarOrdenSalidaHandler::class, fn (Container $c) => new CancelarOrdenSalidaHandler(
    $c->get(SalidaOrdenRepositoryInterface::class),
    $c->get(LpnRepositoryInterface::class),
    $c->get(UnitOfWork::class),
));
$container->set(VerOrdenSalidaHandler::class, fn (Container $c) => new VerOrdenSalidaHandler($c->get(SalidaOrdenRepositoryInterface::class)));
$container->set(ListarOrdenesSalidaHandler::class, fn (Container $c) => new ListarOrdenesSalidaHandler($c->get(SalidaOrdenRepositoryInterface::class)));

// Reempaque
$container->set(ReempaqueOrdenRepositoryInterface::class, fn (Container $c) => new PdoReempaqueOrdenRepository($c->get(PDO::class)));
$container->set(GenealogiaLpnRepositoryInterface::class, fn (Container $c) => new PdoGenealogiaLpnRepository($c->get(PDO::class)));

$container->set(IniciarReempaqueHandler::class, fn (Container $c) => new IniciarReempaqueHandler(
    $c->get(LpnRepositoryInterface::class),
    $c->get(ReempaqueOrdenRepositoryInterface::class),
    $c->get(UnitOfWork::class),
));
$container->set(CompletarReempaqueHandler::class, fn (Container $c) => new CompletarReempaqueHandler(
    $c->get(ReempaqueOrdenRepositoryInterface::class),
    $c->get(LpnRepositoryInterface::class),
    $c->get(GenealogiaLpnRepositoryInterface::class),
    $c->get(LpnBarcodeGeneratorInterface::class),
    $c->get(PdoEtiquetaRepository::class),
    $c->get(UnitOfWork::class),
));
$container->set(CancelarReempaqueHandler::class, fn (Container $c) => new CancelarReempaqueHandler(
    $c->get(ReempaqueOrdenRepositoryInterface::class),
    $c->get(LpnRepositoryInterface::class),
    $c->get(UnitOfWork::class),
));
$container->set(VerReempaqueOrdenHandler::class, fn (Container $c) => new VerReempaqueOrdenHandler($c->get(ReempaqueOrdenRepositoryInterface::class)));
$container->set(ListarReempaqueOrdenesHandler::class, fn (Container $c) => new ListarReempaqueOrdenesHandler($c->get(ReempaqueOrdenRepositoryInterface::class)));
$container->set(ConsultarGenealogiaLpnHandler::class, fn (Container $c) => new ConsultarGenealogiaLpnHandler($c->get(GenealogiaLpnRepositoryInterface::class)));

// Cliente
$container->set(ClienteRepositoryInterface::class, fn (Container $c) => new PdoClienteRepository($c->get(PDO::class)));
$container->set(ClienteDocumentoRepositoryInterface::class, fn (Container $c) => new PdoClienteDocumentoRepository($c->get(PDO::class)));
$container->set(ClienteDocumentoStorage::class, fn () => new ClienteDocumentoStorage(dirname(__DIR__) . '/public'));

$container->set(RegistrarClienteHandler::class, fn (Container $c) => new RegistrarClienteHandler($c->get(ClienteRepositoryInterface::class)));
$container->set(ListarClientesHandler::class, fn (Container $c) => new ListarClientesHandler($c->get(ClienteRepositoryInterface::class)));
$container->set(ListarClientesPendientesHandler::class, fn (Container $c) => new ListarClientesPendientesHandler($c->get(ClienteRepositoryInterface::class)));
$container->set(BuscarClientesHandler::class, fn (Container $c) => new BuscarClientesHandler($c->get(ClienteRepositoryInterface::class)));
$container->set(ActualizarClienteHandler::class, fn (Container $c) => new ActualizarClienteHandler($c->get(ClienteRepositoryInterface::class)));
$container->set(DesactivarClienteHandler::class, fn (Container $c) => new DesactivarClienteHandler($c->get(ClienteRepositoryInterface::class)));
$container->set(RechazarClienteHandler::class, fn (Container $c) => new RechazarClienteHandler($c->get(ClienteRepositoryInterface::class)));

$container->set(SolicitarRegistroClienteHandler::class, fn (Container $c) => new SolicitarRegistroClienteHandler(
    $c->get(ClienteRepositoryInterface::class),
    $c->get(ClienteDocumentoRepositoryInterface::class),
    $c->get(UnitOfWork::class),
));

$container->set(AprobarClienteHandler::class, fn (Container $c) => new AprobarClienteHandler(
    $c->get(ClienteRepositoryInterface::class),
    $c->get(UsuarioRepositoryInterface::class),
    $c->get(PasswordHasher::class),
    $c->get(EmailServiceInterface::class),
    $c->get(Logger::class),
    $c->get(UnitOfWork::class),
));

// Aduanero
$container->set(AduaneroRepositoryInterface::class, fn (Container $c) => new PdoAduaneroRepository($c->get(PDO::class)));
$container->set(RegistrarAduaneroHandler::class, fn (Container $c) => new RegistrarAduaneroHandler($c->get(AduaneroRepositoryInterface::class)));
$container->set(ListarAduanerosHandler::class, fn (Container $c) => new ListarAduanerosHandler($c->get(AduaneroRepositoryInterface::class)));
$container->set(ActualizarAduaneroHandler::class, fn (Container $c) => new ActualizarAduaneroHandler($c->get(AduaneroRepositoryInterface::class)));
$container->set(BuscarAduanerosHandler::class, fn (Container $c) => new BuscarAduanerosHandler($c->get(AduaneroRepositoryInterface::class)));

// Proveedor
$container->set(ProveedorRepositoryInterface::class, fn (Container $c) => new PdoProveedorRepository($c->get(PDO::class)));
$container->set(RegistrarProveedorHandler::class, fn (Container $c) => new RegistrarProveedorHandler($c->get(ProveedorRepositoryInterface::class)));
$container->set(ListarProveedoresHandler::class, fn (Container $c) => new ListarProveedoresHandler($c->get(ProveedorRepositoryInterface::class)));
$container->set(ActualizarProveedorHandler::class, fn (Container $c) => new ActualizarProveedorHandler($c->get(ProveedorRepositoryInterface::class)));
$container->set(BuscarProveedoresHandler::class, fn (Container $c) => new BuscarProveedoresHandler($c->get(ProveedorRepositoryInterface::class)));

// Pais
$container->set(PaisRepositoryInterface::class, fn (Container $c) => new PdoPaisRepository($c->get(PDO::class)));
$container->set(RegistrarPaisHandler::class, fn (Container $c) => new RegistrarPaisHandler($c->get(PaisRepositoryInterface::class)));
$container->set(ListarPaisesHandler::class, fn (Container $c) => new ListarPaisesHandler($c->get(PaisRepositoryInterface::class)));
$container->set(ActualizarPaisHandler::class, fn (Container $c) => new ActualizarPaisHandler($c->get(PaisRepositoryInterface::class)));
$container->set(BuscarPaisesHandler::class, fn (Container $c) => new BuscarPaisesHandler($c->get(PaisRepositoryInterface::class)));

// Configuracion
$container->set(SecretCipher::class, fn () => new SecretCipher());
$container->set(ConfiguracionRepositoryInterface::class, fn (Container $c) => new PdoConfiguracionRepository(
    $c->get(PDO::class),
    $c->get(SecretCipher::class),
));
$container->set(GuardarConfiguracionSmtpHandler::class, fn (Container $c) => new GuardarConfiguracionSmtpHandler($c->get(ConfiguracionRepositoryInterface::class)));
$container->set(GuardarDatosEmpresaHandler::class, fn (Container $c) => new GuardarDatosEmpresaHandler($c->get(ConfiguracionRepositoryInterface::class)));
$container->set(GuardarPreferenciasNotificacionHandler::class, fn (Container $c) => new GuardarPreferenciasNotificacionHandler($c->get(ConfiguracionRepositoryInterface::class)));
$container->set(ProbadorSmtpInterface::class, fn (Container $c) => $c->get(EmailServiceInterface::class));

// Notificacion
$container->set(EmailServiceInterface::class, fn (Container $c) => new PhpMailerEmailService(
    $c->get(Logger::class),
    $c->get(ConfiguracionRepositoryInterface::class),
));
$container->set(NotificacionRepositoryInterface::class, fn (Container $c) => new PdoNotificacionRepository($c->get(PDO::class)));
$container->set(NotificarIngresoCargaHandler::class, fn (Container $c) => new NotificarIngresoCargaHandler(
    $c->get(EmailServiceInterface::class),
    $c->get(NotificacionRepositoryInterface::class),
    $c->get(ConfiguracionRepositoryInterface::class),
));

$container->set(RegistrarCargaHandler::class, fn (Container $c) => new RegistrarCargaHandler(
    $c->get(CargaRepositoryInterface::class),
    $c->get(TrackingNumeroGeneratorInterface::class),
    $c->get(BarcodeGeneratorInterface::class),
    $c->get(PdoHistorialEstadoRepository::class),
    $c->get(PdoEtiquetaRepository::class),
    $c->get(ClienteRepositoryInterface::class),
    $c->get(NotificarIngresoCargaHandler::class),
    $c->get(UnitOfWork::class),
));

$container->set(ActualizarEstadoCargaHandler::class, fn (Container $c) => new ActualizarEstadoCargaHandler(
    $c->get(CargaRepositoryInterface::class),
    $c->get(PdoHistorialEstadoRepository::class),
    $c->get(UnitOfWork::class),
));

$container->set(ConsultarTrackingPorNumeroHandler::class, fn (Container $c) => new ConsultarTrackingPorNumeroHandler(
    $c->get(CargaRepositoryInterface::class),
    $c->get(PdoHistorialEstadoRepository::class),
    $c->get(LpnRepositoryInterface::class),
));

$container->set(ConsultarTrackingPorContenedorHandler::class, fn (Container $c) => new ConsultarTrackingPorContenedorHandler(
    $c->get(CargaRepositoryInterface::class),
    $c->get(PdoHistorialEstadoRepository::class),
    $c->get(LpnRepositoryInterface::class),
));

$container->set(ListarCargasPorClienteHandler::class, fn (Container $c) => new ListarCargasPorClienteHandler(
    $c->get(CargaRepositoryInterface::class),
));

$container->set(ListarCargasHandler::class, fn (Container $c) => new ListarCargasHandler($c->get(CargaRepositoryInterface::class)));
$container->set(BuscarCargasHandler::class, fn (Container $c) => new BuscarCargasHandler($c->get(CargaRepositoryInterface::class)));
$container->set(ActualizarDatosCargaHandler::class, fn (Container $c) => new ActualizarDatosCargaHandler($c->get(CargaRepositoryInterface::class)));
$container->set(ActaRecepcionRepositoryInterface::class, fn (Container $c) => new PdoActaRecepcionRepository($c->get(PDO::class)));
$container->set(GuardarActaRecepcionHandler::class, fn (Container $c) => new GuardarActaRecepcionHandler(
    $c->get(CargaRepositoryInterface::class),
    $c->get(ActaRecepcionRepositoryInterface::class),
    $c->get(ClienteRepositoryInterface::class),
));
$container->set(VincularActaRecepcionHandler::class, fn (Container $c) => new VincularActaRecepcionHandler(
    $c->get(CargaRepositoryInterface::class),
    $c->get(ActaRecepcionRepositoryInterface::class),
));

// Factura
$container->set(FacturaRepositoryInterface::class, fn (Container $c) => new PdoFacturaRepository($c->get(PDO::class)));

$container->set(CrearFacturaHandler::class, fn (Container $c) => new CrearFacturaHandler(
    $c->get(ClienteRepositoryInterface::class),
    $c->get(CargaRepositoryInterface::class),
    $c->get(FacturaRepositoryInterface::class),
    $c->get(UnitOfWork::class),
));
$container->set(MarcarFacturaPagadaHandler::class, fn (Container $c) => new MarcarFacturaPagadaHandler(
    $c->get(FacturaRepositoryInterface::class),
    $c->get(UnitOfWork::class),
));
$container->set(AnularFacturaHandler::class, fn (Container $c) => new AnularFacturaHandler(
    $c->get(FacturaRepositoryInterface::class),
    $c->get(UnitOfWork::class),
));
$container->set(VerFacturaHandler::class, fn (Container $c) => new VerFacturaHandler($c->get(FacturaRepositoryInterface::class)));
$container->set(ListarFacturasHandler::class, fn (Container $c) => new ListarFacturasHandler($c->get(FacturaRepositoryInterface::class)));

// Reporte
$container->set(PdoResumenDashboardRepository::class, fn (Container $c) => new PdoResumenDashboardRepository($c->get(PDO::class)));
$container->set(ObtenerResumenDashboardHandler::class, fn (Container $c) => new ObtenerResumenDashboardHandler(
    $c->get(PdoResumenDashboardRepository::class),
));
$container->set(PdoReporteOperativoRepository::class, fn (Container $c) => new PdoReporteOperativoRepository($c->get(PDO::class)));
$container->set(ObtenerReporteOperativoHandler::class, fn (Container $c) => new ObtenerReporteOperativoHandler(
    $c->get(PdoReporteOperativoRepository::class),
));

return $container;
