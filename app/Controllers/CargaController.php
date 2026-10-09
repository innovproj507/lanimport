<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Aduanero\Application\ListarAduaneros\ListarAduanerosHandler;
use Courier\Aduanero\Application\ListarAduaneros\ListarAduanerosQuery;
use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Carga\Application\ActualizarDatosCarga\ActualizarDatosCargaCommand;
use Courier\Carga\Application\ActualizarDatosCarga\ActualizarDatosCargaHandler;
use Courier\Carga\Application\BuscarCargas\BuscarCargasQuery;
use Courier\Carga\Application\BuscarCargas\BuscarCargasHandler;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroHandler;
use Courier\Carga\Application\RegistrarCarga\RegistrarCargaCommand;
use Courier\Carga\Application\RegistrarCarga\RegistrarCargaHandler;
use Courier\Carga\Application\VincularActaRecepcion\VincularActaRecepcionCommand;
use Courier\Carga\Application\VincularActaRecepcion\VincularActaRecepcionHandler;
use Courier\Carga\Domain\ActaRecepcionRepositoryInterface;
use Courier\Carga\Domain\Carga;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Carga\Infrastructure\Storage\CargaDocumentoStorage;
use Courier\Cliente\Application\ListarClientes\ListarClientesHandler;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Notificacion\Application\NotificarIngresoCarga\NotificarIngresoCargaHandler;
use Courier\Notificacion\Domain\NotificacionRepositoryInterface;
use Courier\Cliente\Application\ListarClientes\ListarClientesQuery;
use Courier\Lpn\Application\EliminarLpnsDeCarga\EliminarLpnsDeCargaCommand;
use Courier\Lpn\Application\EliminarLpnsDeCarga\EliminarLpnsDeCargaHandler;
use Courier\Lpn\Application\GenerarLpnsParaCarga\GenerarLpnsParaCargaCommand;
use Courier\Lpn\Application\GenerarLpnsParaCarga\GenerarLpnsParaCargaHandler;
use Courier\Lpn\Domain\EtiquetaLpnConfigRepositoryInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Pais\Application\ListarPaises\ListarPaisesHandler;
use Courier\Pais\Application\ListarPaises\ListarPaisesQuery;
use Courier\Proveedor\Application\ListarProveedores\ListarProveedoresHandler;
use Courier\Proveedor\Application\ListarProveedores\ListarProveedoresQuery;
use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class CargaController extends BaseController
{
    public function redirectToIngreso(): RedirectResponse
    {
        return redirect()->to('/cargas/ingreso');
    }

    public function showIngresoForm(): string
    {
        // ?acta=ID: viene de "Registrar carga con esta acta" (mercancia ya recibida por bodega).
        $acta = (string) ($this->request->getGet('acta') ?? '');

        return $this->renderIngreso(null, $acta !== '' ? $acta : null);
    }

    public function store(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderIngreso('Sesion expirada, por favor intente de nuevo.');
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);
        $actaId = (string) ($this->request->getPost('acta_recepcion_id') ?? '');

        try {
            /** @var ProveedorRepositoryInterface $proveedorRepository */
            $proveedorRepository = $this->container()->get(ProveedorRepositoryInterface::class);
            $proveedorIdentificador = (string) $this->request->getPost('proveedor_identificador');
            $proveedor = $proveedorRepository->findByIdentificador($proveedorIdentificador);

            if ($proveedor === null) {
                throw new ValidationException('Seleccione un proveedor valido.');
            }

            $aduanero = null;
            $aduaneroIdentificador = (string) $this->request->getPost('aduanero_identificador');

            if ($aduaneroIdentificador !== '') {
                /** @var AduaneroRepositoryInterface $aduaneroRepository */
                $aduaneroRepository = $this->container()->get(AduaneroRepositoryInterface::class);
                $aduanero = $aduaneroRepository->findByIdentificador($aduaneroIdentificador);

                if ($aduanero === null) {
                    throw new ValidationException('Seleccione un aduanero valido.');
                }
            }

            /** @var VincularActaRecepcionHandler $vincularActa */
            $vincularActa = $this->container()->get(VincularActaRecepcionHandler::class);

            if ($actaId !== '') {
                // Se valida antes de crear la carga, para no dejarla registrada si el acta no corresponde.
                $vincularActa->validar($actaId, null, (string) $this->request->getPost('cliente_id'));
            }

            $facturaProveedor = $this->procesarFacturaProveedor();

            $command = new RegistrarCargaCommand(
                (string) $this->request->getPost('cliente_id'),
                $proveedor->nombre(),
                $proveedor->identificador(),
                $aduanero?->identificador(),
                $aduanero?->nombre(),
                $this->request->getPost('numero_contenedor') !== '' ? (string) $this->request->getPost('numero_contenedor') : null,
                null,
                [],
                (string) $sessionManager->currentUsuarioId(),
                (string) $this->request->getPost('fecha_ingreso'),
                $this->lineasDesdeFormulario(),
                $this->request->getPost('notas') !== '' ? (string) $this->request->getPost('notas') : null,
                $facturaProveedor['nombre_archivo'] ?? null,
                $facturaProveedor['ruta_archivo'] ?? null,
            );

            $result = $this->container()->get(RegistrarCargaHandler::class)->handle($command);

            if ($actaId !== '') {
                $vincularActa->handle(new VincularActaRecepcionCommand($actaId, $result->cargaId));
            }

            $this->avisoCorreo('Carga ' . $result->trackingNumero . ' registrada.', $result->correoEstado, $result->correoDestinatario, $result->correoError);

            return redirect()->to('/cargas/' . $result->cargaId);
        } catch (ValidationException $e) {
            return $this->renderIngreso($e->getMessage(), $actaId !== '' ? $actaId : null);
        }
    }

    private function renderIngreso(?string $error, ?string $actaPreseleccionada = null): string
    {
        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());
        $aduaneros = $this->container()->get(ListarAduanerosHandler::class)->handle(new ListarAduanerosQuery());
        $proveedores = $this->container()->get(ListarProveedoresHandler::class)->handle(new ListarProveedoresQuery());
        $paises = $this->container()->get(ListarPaisesHandler::class)->handle(new ListarPaisesQuery());

        $resultado = $this->container()->get(BuscarCargasHandler::class)->handle(new BuscarCargasQuery('', null, 1, 10));

        return view('carga/ingreso', [
            'error' => $error,
            'clientes' => $clientes,
            'aduaneros' => $aduaneros,
            'proveedores' => $proveedores,
            'paises' => $paises,
            'cargas' => $resultado->items,
            'total' => $resultado->total,
            'page' => $resultado->page,
            'perPage' => $resultado->perPage,
            'totalPages' => $resultado->totalPages(),
            'nombresClientes' => $this->nombresClientes($clientes),
            'actasSinCarga' => $this->container()->get(ActaRecepcionRepositoryInterface::class)->actasSinCarga(),
            'actaPreseleccionada' => $actaPreseleccionada,
            'mostrarFormulario' => $error !== null || $actaPreseleccionada !== null,
        ]);
    }

    public function buscar(): ResponseInterface
    {
        $termino = (string) ($this->request->getGet('q') ?? '');
        $estado = (string) ($this->request->getGet('estado') ?? '');
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarCargasHandler::class)->handle(
            new BuscarCargasQuery($termino, $estado !== '' ? $estado : null, $page, $perPage)
        );

        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());

        $html = view('carga/_tabla_filas', [
            'cargas' => $resultado->items,
            'nombresClientes' => $this->nombresClientes($clientes),
        ], ['debug' => false]);

        $paginacion = view('carga/_paginacion', [
            'total' => $resultado->total,
            'page' => $resultado->page,
            'perPage' => $resultado->perPage,
            'totalPages' => $resultado->totalPages(),
        ], ['debug' => false]);

        return $this->response->setJSON(['html' => $html, 'paginacion' => $paginacion]);
    }

    private function perPageDesdeRequest(): int
    {
        $perPage = (int) ($this->request->getGet('per_page') ?? 10);

        return in_array($perPage, [5, 10, 25], true) ? $perPage : 10;
    }

    /**
     * @param array<int, \Courier\Cliente\Domain\Cliente> $clientes
     * @return array<string, string>
     */
    private function nombresClientes(array $clientes): array
    {
        $nombresClientes = [];

        foreach ($clientes as $cliente) {
            $nombresClientes[$cliente->id()] = $cliente->nombreCompleto();
        }

        return $nombresClientes;
    }

    public function showDetalle(string $id): string
    {
        return $this->renderDetalle($id, null);
    }

    private function renderDetalle(string $id, ?string $error): string
    {
        /** @var CargaRepositoryInterface $cargas */
        $cargas = $this->container()->get(CargaRepositoryInterface::class);
        $carga = $cargas->findById($id);

        if ($carga === null) {
            $this->response->setStatusCode(404);

            return 'Carga no encontrada.';
        }

        $view = ConsultarTrackingPorNumeroHandler::toView($carga, $this->container()->get(PdoHistorialEstadoRepository::class), $this->container()->get(LpnRepositoryInterface::class));
        $lpnsGenerados = $this->container()->get(LpnRepositoryInterface::class)->existsLpnsParaCarga($carga->id());
        $etiquetaConfig = $this->container()->get(EtiquetaLpnConfigRepositoryInterface::class)->obtener();

        return view('carga/detalle', [
            'view' => $view,
            'lpnsGenerados' => $lpnsGenerados,
            'etiquetaConfig' => $etiquetaConfig,
            'tieneActaRecepcion' => $this->container()->get(ActaRecepcionRepositoryInterface::class)->findByCargaId($carga->id()) !== null,
            'correos' => $this->container()->get(NotificacionRepositoryInterface::class)->listarPorCarga($carga->id()),
            'aviso' => $this->tomarAviso(),
            'error' => $error,
        ]);
    }

    /** Reenvia al cliente el correo de "su carga ingreso a bodega" (p.ej. tras corregir el SMTP). */
    public function reenviarCorreo(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/cargas/' . $id);
        }

        $carga = $this->container()->get(CargaRepositoryInterface::class)->findById($id);

        if ($carga === null) {
            $this->response->setStatusCode(404);

            return 'Carga no encontrada.';
        }

        $cliente = $this->container()->get(ClienteRepositoryInterface::class)->findById($carga->clienteId());

        if ($cliente === null) {
            return $this->renderDetalle($id, 'La carga no tiene un cliente valido para enviarle el correo.');
        }

        $notificacion = $this->container()->get(NotificarIngresoCargaHandler::class)->handle(
            $carga->id(),
            (string) $cliente->email(),
            $cliente->nombreCompleto(),
            (string) $carga->trackingNumero(),
            $carga->detalle()?->fechaEstimadaLlegada(),
            $carga->fechaIngreso(),
            $carga->proveedor(),
            array_sum(array_map(static fn ($linea) => $linea->cantidad(), $carga->lineas())),
            true,
        );

        $this->avisoCorreo('', $notificacion?->estadoEnvio()->value, $notificacion?->destinatarioEmail(), $notificacion?->errorMensaje());

        return redirect()->to('/cargas/' . $id);
    }

    /** Guarda en sesion el aviso que se muestra una vez en el detalle de la carga. */
    private function avisoCorreo(string $prefijo, ?string $estado, ?string $destinatario, ?string $error): void
    {
        $prefijo = $prefijo !== '' ? $prefijo . ' ' : '';

        $_SESSION['carga_aviso'] = match ($estado) {
            'enviado' => ['tipo' => 'ok', 'texto' => $prefijo . "Se envio el correo de aviso al cliente ({$destinatario})."],
            'fallido' => ['tipo' => 'error', 'texto' => $prefijo . "No se pudo enviar el correo al cliente ({$destinatario}): {$error}"],
            default => ['tipo' => 'info', 'texto' => $prefijo . 'El aviso por correo al cliente esta desactivado en Configuracion > Notificaciones.'],
        };
    }

    /** @return array{tipo: string, texto: string}|null */
    private function tomarAviso(): ?array
    {
        $aviso = $_SESSION['carga_aviso'] ?? null;
        unset($_SESSION['carga_aviso']);

        return $aviso;
    }

    public function showEditForm(string $id): string
    {
        /** @var CargaRepositoryInterface $cargas */
        $cargas = $this->container()->get(CargaRepositoryInterface::class);
        $carga = $cargas->findById($id);

        if ($carga === null) {
            $this->response->setStatusCode(404);

            return 'Carga no encontrada.';
        }

        return $this->renderEditar($carga, null);
    }

    private function renderEditar(Carga $carga, ?string $error): string
    {
        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());
        $aduaneros = $this->container()->get(ListarAduanerosHandler::class)->handle(new ListarAduanerosQuery());
        $proveedores = $this->container()->get(ListarProveedoresHandler::class)->handle(new ListarProveedoresQuery());
        $paises = $this->container()->get(ListarPaisesHandler::class)->handle(new ListarPaisesQuery());
        $lpnsGenerados = $this->container()->get(LpnRepositoryInterface::class)->existsLpnsParaCarga($carga->id());

        return view('carga/editar', [
            'carga' => $carga,
            'clientes' => $clientes,
            'aduaneros' => $aduaneros,
            'proveedores' => $proveedores,
            'paises' => $paises,
            'lpnsGenerados' => $lpnsGenerados,
            'error' => $error,
        ]);
    }

    public function update(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/cargas/' . $id . '/editar');
        }

        /** @var CargaRepositoryInterface $cargas */
        $cargas = $this->container()->get(CargaRepositoryInterface::class);
        $carga = $cargas->findById($id);

        if ($carga === null) {
            $this->response->setStatusCode(404);

            return 'Carga no encontrada.';
        }

        try {
            /** @var ProveedorRepositoryInterface $proveedorRepository */
            $proveedorRepository = $this->container()->get(ProveedorRepositoryInterface::class);
            $proveedorIdentificador = (string) $this->request->getPost('proveedor_identificador');
            $proveedor = $proveedorRepository->findByIdentificador($proveedorIdentificador);

            if ($proveedor === null) {
                throw new ValidationException('Seleccione un proveedor valido.');
            }

            $aduanero = null;
            $aduaneroIdentificador = (string) $this->request->getPost('aduanero_identificador');

            if ($aduaneroIdentificador !== '') {
                /** @var AduaneroRepositoryInterface $aduaneroRepository */
                $aduaneroRepository = $this->container()->get(AduaneroRepositoryInterface::class);
                $aduanero = $aduaneroRepository->findByIdentificador($aduaneroIdentificador);

                if ($aduanero === null) {
                    throw new ValidationException('Seleccione un aduanero valido.');
                }
            }

            $facturaProveedor = $this->procesarFacturaProveedor();

            $command = new ActualizarDatosCargaCommand(
                $id,
                (string) $this->request->getPost('cliente_id'),
                $proveedor->nombre(),
                $proveedor->identificador(),
                $aduanero?->identificador(),
                $aduanero?->nombre(),
                $this->request->getPost('numero_contenedor') !== '' ? (string) $this->request->getPost('numero_contenedor') : null,
                (string) $this->request->getPost('fecha_ingreso'),
                $this->lineasDesdeFormulario(),
                $this->request->getPost('notas') !== '' ? (string) $this->request->getPost('notas') : null,
                $facturaProveedor['nombre_archivo'] ?? $carga->facturaProveedorNombre(),
                $facturaProveedor['ruta_archivo'] ?? $carga->facturaProveedorRuta(),
            );

            $this->container()->get(ActualizarDatosCargaHandler::class)->handle($command);

            return redirect()->to('/cargas/' . $id);
        } catch (NotFoundException $e) {
            $this->response->setStatusCode(404);

            return $e->getMessage();
        } catch (ValidationException $e) {
            return $this->renderEditar($carga, $e->getMessage());
        }
    }

    public function generarLpns(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/cargas/' . $id);
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        try {
            $command = new GenerarLpnsParaCargaCommand($id, (string) $sessionManager->currentUsuarioId());
            $this->container()->get(GenerarLpnsParaCargaHandler::class)->handle($command);

            return redirect()->to('/cargas/' . $id . '/recepcion');
        } catch (ValidationException $e) {
            return redirect()->to('/cargas/' . $id);
        }
    }

    public function eliminarLpns(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderDetalle($id, 'Sesion expirada, por favor intente de nuevo.');
        }

        try {
            $command = new EliminarLpnsDeCargaCommand($id);
            $this->container()->get(EliminarLpnsDeCargaHandler::class)->handle($command);

            return redirect()->to('/cargas/' . $id);
        } catch (ValidationException $e) {
            return $this->renderDetalle($id, $e->getMessage());
        }
    }

    private const FACTURA_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];
    private const FACTURA_MAX_SIZE = 5 * 1024 * 1024;

    /** @return array{nombre_archivo: string, ruta_archivo: string}|null */
    private function procesarFacturaProveedor(): ?array
    {
        if (!isset($_FILES['factura_proveedor']) || $_FILES['factura_proveedor']['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($_FILES['factura_proveedor']['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException('No se pudo subir la factura del proveedor.');
        }

        $size = (int) $_FILES['factura_proveedor']['size'];
        $type = (string) $_FILES['factura_proveedor']['type'];

        if ($size > self::FACTURA_MAX_SIZE) {
            throw new ValidationException('La factura del proveedor no puede pesar mas de 5 MB.');
        }

        if (!in_array($type, self::FACTURA_MIME_TYPES, true)) {
            throw new ValidationException('La factura del proveedor debe ser PDF, JPG o PNG.');
        }

        /** @var CargaDocumentoStorage $storage */
        $storage = $this->container()->get(CargaDocumentoStorage::class);

        return $storage->store([
            'name' => (string) $_FILES['factura_proveedor']['name'],
            'type' => $type,
            'tmp_name' => (string) $_FILES['factura_proveedor']['tmp_name'],
            'error' => (int) $_FILES['factura_proveedor']['error'],
            'size' => $size,
        ]);
    }

    /**
     * @return array<int, array{
     *     descripcion: string, unidadMedida: string, cantidad: float,
     *     ancho: float, alto: float, largo: float, peso: float, valor: float, marca: ?string, cubicajePies: ?float
     * }>
     */
    private function lineasDesdeFormulario(): array
    {
        $descripciones = $this->request->getPost('linea_descripcion') ?? [];
        $marcas = $this->request->getPost('linea_marca') ?? [];
        $unidades = $this->request->getPost('linea_unidad_medida') ?? [];
        $cantidades = $this->request->getPost('linea_cantidad') ?? [];
        $anchos = $this->request->getPost('linea_ancho') ?? [];
        $altos = $this->request->getPost('linea_alto') ?? [];
        $largos = $this->request->getPost('linea_largo') ?? [];
        $pesos = $this->request->getPost('linea_peso') ?? [];
        $valores = $this->request->getPost('linea_valor') ?? [];
        $cubicajes = $this->request->getPost('linea_cubicaje') ?? [];

        $descripciones = is_array($descripciones) ? $descripciones : [];
        $marcas = is_array($marcas) ? $marcas : [];
        $unidades = is_array($unidades) ? $unidades : [];
        $cantidades = is_array($cantidades) ? $cantidades : [];
        $anchos = is_array($anchos) ? $anchos : [];
        $altos = is_array($altos) ? $altos : [];
        $largos = is_array($largos) ? $largos : [];
        $pesos = is_array($pesos) ? $pesos : [];
        $valores = is_array($valores) ? $valores : [];
        $cubicajes = is_array($cubicajes) ? $cubicajes : [];

        $lineas = [];

        foreach ($descripciones as $i => $descripcion) {
            $descripcion = trim((string) $descripcion);

            if ($descripcion === '') {
                continue;
            }

            $marca = trim((string) ($marcas[$i] ?? ''));
            $cubicaje = trim((string) ($cubicajes[$i] ?? ''));

            $lineas[] = [
                'descripcion' => $descripcion,
                'marca' => $marca !== '' ? $marca : null,
                'unidadMedida' => (string) ($unidades[$i] ?? ''),
                'cantidad' => (float) ($cantidades[$i] ?? 0),
                'ancho' => (float) ($anchos[$i] ?? 0),
                'alto' => (float) ($altos[$i] ?? 0),
                'largo' => (float) ($largos[$i] ?? 0),
                'peso' => (float) ($pesos[$i] ?? 0),
                'valor' => (float) ($valores[$i] ?? 0),
                'cubicajePies' => $cubicaje !== '' ? (float) $cubicaje : null,
            ];
        }

        return $lineas;
    }
}
