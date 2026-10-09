<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use Courier\Auth\Domain\Rol;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Carga\Application\GuardarActaRecepcion\GuardarActaRecepcionCommand;
use Courier\Carga\Application\GuardarActaRecepcion\GuardarActaRecepcionHandler;
use Courier\Carga\Application\VincularActaRecepcion\VincularActaRecepcionCommand;
use Courier\Carga\Application\VincularActaRecepcion\VincularActaRecepcionHandler;
use Courier\Carga\Domain\ActaRecepcion;
use Courier\Carga\Domain\ActaRecepcionRepositoryInterface;
use Courier\Carga\Domain\Carga;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\Exception\AccionNoPermitidaException;
use Courier\Cliente\Application\ListarClientes\ListarClientesHandler;
use Courier\Cliente\Application\ListarClientes\ListarClientesQuery;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Actas de recepcion (formulario que llena bodega al recibir la mercancia).
 * Bodega puede llenarla antes de que exista la carga; operaciones la vincula
 * despues desde Ingreso de Carga o desde el acta. La llenan bodega/admin, la
 * corrigen bodega/gerente/admin y la puede ver/imprimir cualquier usuario interno.
 */
final class ActaRecepcionController extends BaseController
{
    private const ROLES_VINCULAR = ['operaciones', 'bodega', 'gerente', 'admin'];

    /** Bandeja: actas esperando carga, cargas sin acta y ultimas actas. */
    public function index(): string
    {
        $actas = $this->actas();

        return view('carga/recepciones', [
            'actasSinCarga' => $actas->actasSinCarga(),
            'cargasPendientes' => $actas->cargasPendientes(),
            'recientes' => $actas->actasRecientes(),
            'puedeLlenar' => ActaRecepcion::puedeRegistrar($this->rol()),
            'puedeVincular' => in_array($this->rolTexto(), self::ROLES_VINCULAR, true),
        ]);
    }

    /** Formulario de acta nueva; con ?carga=ID queda asociada a esa carga desde el inicio. */
    public function nueva(): RedirectResponse|string
    {
        $carga = $this->cargaDesdeParametro((string) ($this->request->getGet('carga') ?? ''));

        if ($carga !== null) {
            $existente = $this->actas()->findByCargaId($carga->id());

            if ($existente !== null) {
                return redirect()->to('/recepciones/' . $existente->id());
            }
        }

        return view('carga/acta_recepcion', $this->datosVista($carga, null, null));
    }

    public function crear(): RedirectResponse|string
    {
        $carga = $this->cargaDesdeParametro((string) ($this->request->getPost('carga_id') ?? ''));

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('carga/acta_recepcion', $this->datosVista($carga, null, 'Sesion expirada, por favor intente de nuevo.', $this->request->getPost()));
        }

        return $this->guardar(null, $carga, null);
    }

    public function show(string $actaId): string
    {
        $acta = $this->actas()->findById($actaId);

        if ($acta === null) {
            $this->response->setStatusCode(404);

            return 'Acta de recepcion no encontrada.';
        }

        return view('carga/acta_recepcion', $this->datosVista($this->cargaDeActa($acta), $acta, $this->mensajeFlash()));
    }

    public function corregir(string $actaId): RedirectResponse|string
    {
        $acta = $this->actas()->findById($actaId);

        if ($acta === null) {
            $this->response->setStatusCode(404);

            return 'Acta de recepcion no encontrada.';
        }

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('carga/acta_recepcion', $this->datosVista($this->cargaDeActa($acta), $acta, 'Sesion expirada, por favor intente de nuevo.'));
        }

        return $this->guardar($acta, $this->cargaDeActa($acta), $acta->id());
    }

    public function imprimir(string $actaId): string
    {
        $acta = $this->actas()->findById($actaId);

        if ($acta === null) {
            $this->response->setStatusCode(404);

            return 'Acta de recepcion no encontrada.';
        }

        return view('carga/acta_recepcion_imprimir', $this->datosVista($this->cargaDeActa($acta), $acta, null));
    }

    /** Vincula un acta sin carga con una carga ya registrada del mismo cliente. */
    public function vincular(string $actaId): RedirectResponse|string
    {
        $acta = $this->actas()->findById($actaId);

        if ($acta === null) {
            $this->response->setStatusCode(404);

            return 'Acta de recepcion no encontrada.';
        }

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/recepciones/' . $actaId);
        }

        try {
            $this->container()->get(VincularActaRecepcionHandler::class)->handle(
                new VincularActaRecepcionCommand($actaId, (string) $this->request->getPost('carga_id'))
            );
            $_SESSION['acta_mensaje'] = 'Acta vinculada a la carga.';

            return redirect()->to('/recepciones/' . $actaId);
        } catch (ValidationException $e) {
            return view('carga/acta_recepcion', $this->datosVista(null, $acta, $e->getMessage()));
        }
    }

    /** Enlace viejo /cargas/{id}/acta-recepcion: lleva al acta de la carga, o a crearla. */
    public function porCarga(string $cargaId): RedirectResponse|string
    {
        $carga = $this->container()->get(CargaRepositoryInterface::class)->findById($cargaId);

        if ($carga === null) {
            $this->response->setStatusCode(404);

            return 'Carga no encontrada.';
        }

        $acta = $this->actas()->findByCargaId($cargaId);

        if ($acta !== null) {
            return redirect()->to('/recepciones/' . $acta->id());
        }

        if (ActaRecepcion::puedeRegistrar($this->rol())) {
            return redirect()->to('/recepciones/nueva?carga=' . $cargaId);
        }

        return view('carga/acta_recepcion', $this->datosVista($carga, null, null));
    }

    private function guardar(?ActaRecepcion $acta, ?Carga $carga, ?string $actaId): RedirectResponse|string
    {
        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        $bultos = (int) $this->request->getPost('cantidad_bultos');
        $rollos = (int) $this->request->getPost('cantidad_rollos');
        $total = trim((string) $this->request->getPost('total_recibido'));
        $tipos = $this->request->getPost('tipos_mercancia') ?? [];

        try {
            $id = $this->container()->get(GuardarActaRecepcionHandler::class)->handle(new GuardarActaRecepcionCommand(
                $actaId,
                $actaId === null ? $carga?->id() : null,
                (string) $this->request->getPost('cliente_id'),
                $this->textoOpcional('marca'),
                $this->textoOpcional('empresa_transporte'),
                $this->textoOpcional('chofer_nombre'),
                $this->textoOpcional('placa'),
                $bultos,
                $rollos,
                $total !== '' ? (int) $total : $bultos + $rollos,
                is_array($tipos) ? array_map('strval', $tipos) : [],
                $this->textoOpcional('descripcion_mercancia'),
                trim((string) $this->request->getPost('entregado_por')),
                trim((string) $this->request->getPost('entregado_por_cedula')),
                (string) $sessionManager->currentUsuarioId(),
                $sessionManager->currentRol(),
            ));
            $_SESSION['acta_mensaje'] = $actaId === null ? 'Acta de recepcion guardada.' : 'Correccion guardada.';

            return redirect()->to('/recepciones/' . $id);
        } catch (NotFoundException $e) {
            $this->response->setStatusCode(404);

            return $e->getMessage();
        } catch (AccionNoPermitidaException $e) {
            $this->response->setStatusCode(403);

            return view('carga/acta_recepcion', $this->datosVista($carga, $acta, $e->getMessage()));
        } catch (ValidationException $e) {
            return view('carga/acta_recepcion', $this->datosVista($carga, $acta, $e->getMessage(), $this->request->getPost()));
        }
    }

    /**
     * @param array<string, mixed>|null $entrada valores enviados, para no perderlos si la validacion falla
     * @return array<string, mixed>
     */
    private function datosVista(?Carga $carga, ?ActaRecepcion $acta, ?string $error, ?array $entrada = null): array
    {
        $carga ??= $acta !== null ? $this->cargaDeActa($acta) : null;
        /** @var UsuarioRepositoryInterface $usuarios */
        $usuarios = $this->container()->get(UsuarioRepositoryInterface::class);
        $clienteId = $acta?->clienteId() ?? $carga?->clienteId();
        $cliente = $clienteId !== null ? $this->container()->get(ClienteRepositoryInterface::class)->findById($clienteId) : null;
        $puedeEditar = $acta === null ? ActaRecepcion::puedeRegistrar($this->rol()) : ActaRecepcion::puedeCorregir($this->rol());

        // Marca por defecto de un acta nueva: las marcas de las lineas de la carga.
        $marcasCarga = [];
        foreach ($carga?->lineas() ?? [] as $linea) {
            if ($linea->marca() !== null && $linea->marca() !== '') {
                $marcasCarga[$linea->marca()] = true;
            }
        }

        $sinCarga = $acta !== null && $acta->cargaId() === null;

        return [
            'carga' => $carga,
            'acta' => $acta,
            'cliente' => $cliente,
            'marcaPorDefecto' => implode(', ', array_keys($marcasCarga)),
            // El cliente solo se elige cuando no hay carga de por medio.
            'clientes' => $puedeEditar && $carga === null
                ? $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery())
                : [],
            'cargasDelCliente' => $sinCarga
                ? array_values(array_filter($this->actas()->cargasPendientes(), fn (array $c) => $c['clienteId'] === $acta->clienteId()))
                : [],
            'puedeVincular' => $sinCarga && in_array($this->rolTexto(), self::ROLES_VINCULAR, true),
            'verificadoPor' => $acta !== null ? $usuarios->findById($acta->verificadoPorUsuarioId())?->nombre() : null,
            'actualizadoPor' => $acta?->actualizadoPorUsuarioId() !== null ? $usuarios->findById($acta->actualizadoPorUsuarioId())?->nombre() : null,
            'empresa' => $this->container()->get(ConfiguracionRepositoryInterface::class)->datosEmpresa(),
            'puedeEditar' => $puedeEditar,
            'entrada' => $entrada,
            'error' => $error,
            'mensaje' => $error === null ? $this->mensajeFlash() : null,
        ];
    }

    private function cargaDesdeParametro(string $cargaId): ?Carga
    {
        return $cargaId !== '' ? $this->container()->get(CargaRepositoryInterface::class)->findById($cargaId) : null;
    }

    private function cargaDeActa(ActaRecepcion $acta): ?Carga
    {
        return $acta->cargaId() !== null ? $this->container()->get(CargaRepositoryInterface::class)->findById($acta->cargaId()) : null;
    }

    private function actas(): ActaRecepcionRepositoryInterface
    {
        return $this->container()->get(ActaRecepcionRepositoryInterface::class);
    }

    private function rolTexto(): ?string
    {
        return $this->container()->get(SessionManager::class)->currentRol();
    }

    private function rol(): ?Rol
    {
        return Rol::tryFrom((string) $this->rolTexto());
    }

    private function textoOpcional(string $campo): ?string
    {
        $valor = trim((string) $this->request->getPost($campo));

        return $valor !== '' ? $valor : null;
    }

    private function mensajeFlash(): ?string
    {
        $mensaje = $_SESSION['acta_mensaje'] ?? null;
        unset($_SESSION['acta_mensaje']);

        return $mensaje;
    }
}
