<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnCommand;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnHandler;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesHandler;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesQuery;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaCommand;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaHandler;
use Courier\Salida\Application\ListarOrdenesSalida\ListarOrdenesSalidaHandler;
use Courier\Salida\Application\ListarOrdenesSalida\ListarOrdenesSalidaQuery;
use Courier\Salida\Application\RegistrarEscaneoSalida\RegistrarEscaneoSalidaCommand;
use Courier\Salida\Application\RegistrarEscaneoSalida\RegistrarEscaneoSalidaHandler;
use Courier\Salida\Application\VerOrdenSalida\VerOrdenSalidaHandler;
use Courier\Salida\Application\VerOrdenSalida\VerOrdenSalidaQuery;
use Courier\Salida\Domain\SalidaOrden;
use Courier\Shared\Domain\Exception\NotFoundException;
use Throwable;

/**
 * API JSON de la app de bodega (/bodega en PDA/tablet): recepcion de etiquetas,
 * consulta de LPNs y escaneo de salidas. Mismos casos de uso que las pantallas
 * web /recepcion y /salidas; usa la sesion del sistema (rutas bodega/api/*).
 */
final class BodegaApiController extends BaseApiController
{
    public function ubicaciones(): ResponseInterface
    {
        try {
            $ubicaciones = $this->container()->get(ListarUbicacionesHandler::class)->handle(new ListarUbicacionesQuery());

            return $this->success(array_values(array_map(
                static fn ($u) => ['id' => $u->id(), 'codigo' => $u->codigo()],
                array_filter($ubicaciones, static fn ($u) => $u->activo()),
            )));
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** Cargas con etiquetas todavia "En recepcion", con su avance. */
    public function recepcionPendientes(): ResponseInterface
    {
        try {
            return $this->success($this->container()->get(LpnRepositoryInterface::class)->resumenRecepcion());
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** Escaneo de recepcion: el LPN pasa de "En recepcion" a "Disponible". */
    public function recepcionEscanear(): ResponseInterface
    {
        $body = $this->cuerpo();

        try {
            $ubicacionId = trim((string) ($body['ubicacion_id'] ?? ''));
            $lpn = $this->container()->get(ConfirmarRecepcionLpnHandler::class)->handle(new ConfirmarRecepcionLpnCommand(
                (string) ($body['codigo'] ?? ''),
                $ubicacionId !== '' ? $ubicacionId : null,
                $this->usuarioId(),
            ));

            $progreso = $lpn->cargaId() !== null
                ? ($this->container()->get(LpnRepositoryInterface::class)->resumenRecepcion($lpn->cargaId())[0] ?? null)
                : null;

            return $this->success([
                'lpn' => ['codigo' => $lpn->codigo(), 'estado' => $lpn->estado()->value, 'estadoLabel' => $lpn->estado()->label()],
                'progreso' => $progreso,
            ]);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** Ficha de un LPN escaneado: estado, ubicacion, carga y cliente. */
    public function lpn(string $codigo): ResponseInterface
    {
        try {
            $codigo = ConfirmarRecepcionLpnHandler::normalizarCodigo(urldecode($codigo));
            $lpn = $this->container()->get(LpnRepositoryInterface::class)->findByCodigo($codigo);

            if ($lpn === null) {
                throw new NotFoundException("No se encontro ningun LPN con el codigo {$codigo}.");
            }

            $carga = $lpn->cargaId() !== null
                ? $this->container()->get(CargaRepositoryInterface::class)->findById($lpn->cargaId())
                : null;
            $cliente = $this->container()->get(ClienteRepositoryInterface::class)->findById($lpn->clienteId());
            $ubicacion = $lpn->ubicacionId() !== null
                ? $this->container()->get(UbicacionRepositoryInterface::class)->findById($lpn->ubicacionId())
                : null;

            return $this->success([
                'codigo' => $lpn->codigo(),
                'estado' => $lpn->estado()->value,
                'estadoLabel' => $lpn->estado()->label(),
                'marca' => $lpn->marca(),
                'descripcion' => $lpn->descripcion(),
                'bulto' => $lpn->ordenEnCarga(),
                'ubicacion' => $ubicacion?->codigo(),
                'cliente' => $cliente?->nombreCompleto(),
                'carga' => $carga === null ? null : [
                    'id' => $carga->id(),
                    'tracking' => (string) $carga->trackingNumero(),
                    'proveedor' => $carga->proveedor(),
                    'fechaIngreso' => $carga->fechaIngreso()->format('Y-m-d'),
                ],
                'actualizado' => $lpn->updatedAt()->format('Y-m-d H:i'),
            ]);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** Ordenes de salida pendientes de despachar. */
    public function salidas(): ResponseInterface
    {
        try {
            $ordenes = $this->container()->get(ListarOrdenesSalidaHandler::class)->handle(new ListarOrdenesSalidaQuery('pendiente'));

            return $this->success(array_map(fn (SalidaOrden $o) => $this->resumenSalida($o), $ordenes));
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    public function salida(string $id): ResponseInterface
    {
        try {
            $orden = $this->container()->get(VerOrdenSalidaHandler::class)->handle(new VerOrdenSalidaQuery($id));

            if ($orden === null) {
                throw new NotFoundException('Orden de salida no encontrada.');
            }

            $datos = $this->resumenSalida($orden);
            $datos['lineas'] = array_map(static fn ($l) => [
                'codigo' => $l->codigoLpn(),
                'escaneado' => $l->escaneado(),
            ], $orden->lineas());

            return $this->success($datos);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    public function salidaEscanear(string $id): ResponseInterface
    {
        try {
            $codigo = ConfirmarRecepcionLpnHandler::normalizarCodigo((string) ($this->cuerpo()['codigo'] ?? ''));
            $progreso = $this->container()->get(RegistrarEscaneoSalidaHandler::class)->handle(new RegistrarEscaneoSalidaCommand($id, $codigo));

            return $this->success(['codigo' => $codigo, 'progreso' => $progreso]);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** Despacha la orden: exige todos los LPNs escaneados (regla de SalidaOrden::confirmar). */
    public function salidaFinalizar(string $id): ResponseInterface
    {
        try {
            $this->container()->get(ConfirmarSalidaHandler::class)->handle(new ConfirmarSalidaCommand($id, $this->usuarioId()));

            return $this->success(['id' => $id, 'estado' => 'confirmada']);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** @return array<string, mixed> */
    private function resumenSalida(SalidaOrden $orden): array
    {
        $cliente = $this->container()->get(ClienteRepositoryInterface::class)->findById($orden->clienteId());

        return [
            'id' => $orden->id(),
            'do' => $orden->etiquetaDo(),
            'cliente' => $cliente?->nombreCompleto(),
            'contenedor' => $orden->embarque()->contenedorFormateado(),
            'estado' => $orden->estado()->value,
            'progreso' => $orden->progreso(),
            'creada' => $orden->createdAt()->format('Y-m-d'),
        ];
    }

    private function usuarioId(): string
    {
        return (string) $this->container()->get(SessionManager::class)->currentUsuarioId();
    }

    /** @return array<string, mixed> JSON del cuerpo (la PWA envia application/json). */
    private function cuerpo(): array
    {
        $json = $this->request->getJSON(true);

        return is_array($json) ? $json : (array) $this->request->getPost();
    }
}
