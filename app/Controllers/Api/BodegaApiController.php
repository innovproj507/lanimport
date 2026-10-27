<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Evidencia\Domain\FotoEvidenciaRepositoryInterface;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnCommand;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnHandler;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesHandler;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesQuery;
use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Reempaque\Application\AbrirCajaReempaque\AbrirCajaReempaqueCommand;
use Courier\Reempaque\Application\AbrirCajaReempaque\AbrirCajaReempaqueHandler;
use Courier\Reempaque\Application\CerrarCajaReempaque\CerrarCajaReempaqueCommand;
use Courier\Reempaque\Application\CerrarCajaReempaque\CerrarCajaReempaqueHandler;
use Courier\Reempaque\Application\EmpacarBultoReempaque\EmpacarBultoReempaqueCommand;
use Courier\Reempaque\Application\EmpacarBultoReempaque\EmpacarBultoReempaqueHandler;
use Courier\Reempaque\Application\ListarReempaqueOrdenes\ListarReempaqueOrdenesHandler;
use Courier\Reempaque\Application\ListarReempaqueOrdenes\ListarReempaqueOrdenesQuery;
use Courier\Reempaque\Application\TerminarReempaque\TerminarReempaqueCommand;
use Courier\Reempaque\Application\TerminarReempaque\TerminarReempaqueHandler;
use Courier\Reempaque\Application\VerReempaqueOrden\VerReempaqueOrdenHandler;
use Courier\Reempaque\Application\VerReempaqueOrden\VerReempaqueOrdenQuery;
use Courier\Reempaque\Domain\ReempaqueOrden;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaCommand;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaHandler;
use Courier\Salida\Application\ListarOrdenesSalida\ListarOrdenesSalidaHandler;
use Courier\Salida\Application\ListarOrdenesSalida\ListarOrdenesSalidaQuery;
use Courier\Salida\Application\RegistrarEscaneoSalida\RegistrarEscaneoSalidaCommand;
use Courier\Salida\Application\RegistrarEscaneoSalida\RegistrarEscaneoSalidaHandler;
use Courier\Salida\Application\VerOrdenSalida\VerOrdenSalidaHandler;
use Courier\Salida\Application\VerOrdenSalida\VerOrdenSalidaQuery;
use Courier\Salida\Domain\SalidaOrden;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
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

    /** Cargas con etiquetas todavia "En recepcion", con su avance y cuantas fotos de recepcion tienen. */
    public function recepcionPendientes(): ResponseInterface
    {
        try {
            $cargas = $this->container()->get(LpnRepositoryInterface::class)->resumenRecepcion();
            $fotos = $this->container()->get(FotoEvidenciaRepositoryInterface::class)->contarRecepcionPorCargas(array_column($cargas, 'cargaId'));

            return $this->success(array_map(static fn (array $c) => $c + ['fotos' => $fotos[$c['cargaId']] ?? 0], $cargas));
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
                // Lo que aun falta de esa carga, para que bodega sepa que bultos buscar.
                'faltan' => $lpn->cargaId() !== null
                    ? array_values(array_filter($this->bultosDeCarga($lpn->cargaId()), static fn (array $b) => !$b['recibido']))
                    : [],
            ]);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** Bultos de una carga con si ya se escanearon en recepcion (en orden 1/N, 2/N...). */
    public function recepcionBultos(string $cargaId): ResponseInterface
    {
        try {
            return $this->success($this->bultosDeCarga($cargaId));
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** @return array<int, array{codigo: string, numero: ?int, total: int, descripcion: ?string, recibido: bool}> */
    private function bultosDeCarga(string $cargaId): array
    {
        $lpns = $this->container()->get(LpnRepositoryInterface::class)->findByCargaId($cargaId);
        usort($lpns, static fn ($a, $b) => [$a->ordenEnCarga() ?? PHP_INT_MAX, $a->codigo()] <=> [$b->ordenEnCarga() ?? PHP_INT_MAX, $b->codigo()]);
        $total = count($lpns);

        return array_map(static fn ($l) => [
            'codigo' => (string) $l->codigo(),
            'numero' => $l->ordenEnCarga(),
            'total' => $total,
            'descripcion' => $l->descripcion(),
            'recibido' => $l->estado() !== EstadoLpn::RECEIVING,
        ], $lpns);
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
                // Caja reempacada: los bultos originales que lleva.
                'contenido' => $lpn->cargaId() === null ? $this->container()->get(LpnRepositoryInterface::class)->bultosContenidos($lpn->id()) : [],
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
            // Para escanear carga por carga en la PDA.
            // Con las fotos que ya tiene cada carga en esta salida (las fotos son por carga del cliente).
            $fotos = $this->container()->get(FotoEvidenciaRepositoryInterface::class)->contarSalidaPorCarga($orden->id());
            $datos['cargas'] = array_map(
                static fn (array $c) => $c + ['fotos' => $fotos[$c['cargaId']] ?? 0],
                $this->container()->get(SalidaOrdenRepositoryInterface::class)->avancePorCarga($orden->id()),
            );

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
        // Puede ser consolidada: todos los clientes de sus cargas.
        $clientes = $this->container()->get(SalidaOrdenRepositoryInterface::class)->clientesPorOrden([$orden->id()])[$orden->id()] ?? [];

        return [
            'id' => $orden->id(),
            'do' => $orden->etiquetaDo(),
            'cliente' => implode(', ', $clientes),
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

    /** Ordenes de reempaque pendientes (las ordena trafico; bodega las ejecuta). */
    public function reempaques(): ResponseInterface
    {
        try {
            $ordenes = $this->container()->get(ListarReempaqueOrdenesHandler::class)->handle(new ListarReempaqueOrdenesQuery('pendiente'));

            return $this->success(array_map(fn (ReempaqueOrden $o) => $this->resumenReempaque($o), $ordenes));
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    public function reempaque(string $id): ResponseInterface
    {
        try {
            return $this->success($this->detalleReempaque($id));
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** Acciones de bodega sobre la orden; cada una devuelve la orden actualizada. */
    public function reempaqueAccion(string $id, string $accion): ResponseInterface
    {
        try {
            $codigo = (string) ($this->cuerpo()['codigo'] ?? '');
            $resultado = match ($accion) {
                'abrir-caja' => $this->container()->get(AbrirCajaReempaqueHandler::class)->handle(new AbrirCajaReempaqueCommand($id)),
                'empacar' => $this->container()->get(EmpacarBultoReempaqueHandler::class)->handle(new EmpacarBultoReempaqueCommand($id, $codigo)),
                'quitar' => $this->container()->get(EmpacarBultoReempaqueHandler::class)->handle(new EmpacarBultoReempaqueCommand($id, $codigo, true)),
                'cerrar-caja' => $this->container()->get(CerrarCajaReempaqueHandler::class)->handle(new CerrarCajaReempaqueCommand($id)),
                'descartar-caja' => $this->container()->get(CerrarCajaReempaqueHandler::class)->handle(new CerrarCajaReempaqueCommand($id, true)),
                'terminar' => $this->container()->get(TerminarReempaqueHandler::class)->handle(new TerminarReempaqueCommand($id)),
                default => throw new NotFoundException('Accion no valida.'),
            };

            return $this->success(['resultado' => $resultado, 'orden' => $this->detalleReempaque($id)]);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    /** @return array<string, mixed> */
    private function resumenReempaque(ReempaqueOrden $orden): array
    {
        $cliente = $this->container()->get(ClienteRepositoryInterface::class)->findById($orden->clienteId());

        return [
            'id' => $orden->id(),
            'codigo' => $orden->codigo(),
            'cliente' => $cliente?->nombreCompleto(),
            'tipo' => $orden->tipo()->label(),
            'instrucciones' => $orden->instrucciones(),
            'estado' => $orden->estado()->value,
            'progreso' => $orden->progreso(),
        ];
    }

    /** @return array<string, mixed> */
    private function detalleReempaque(string $id): array
    {
        $orden = $this->container()->get(VerReempaqueOrdenHandler::class)->handle(new VerReempaqueOrdenQuery($id));

        if ($orden === null) {
            throw new NotFoundException('Orden de reempaque no encontrada.');
        }

        $lpns = $this->container()->get(LpnRepositoryInterface::class);
        $info = function (string $lpnId) use ($lpns): array {
            $lpn = $lpns->findById($lpnId);

            return ['codigo' => (string) $lpn?->codigo(), 'marca' => $lpn?->marca()];
        };

        $datos = $this->resumenReempaque($orden);
        // Para bodega "empacado" es estar en cualquier caja, tambien la que esta llenando.
        $enCajas = array_unique(array_merge(...array_values(array_column($orden->cajas(), 'contenido')) ?: [[]]));
        $datos['pendientes'] = array_map($info, array_values(array_diff($orden->origenLpnIds(), $enCajas)));
        $datos['progreso']['empacados'] = count($enCajas);
        $datos['cajas'] = [];

        foreach ($orden->cajas() as $cajaId => $caja) {
            $datos['cajas'][] = [
                'codigo' => $info($cajaId)['codigo'],
                'cerrada' => $caja['cerrada'],
                'contenido' => array_map(static fn (string $o) => $info($o)['codigo'], $caja['contenido']),
            ];
        }

        $abierta = $orden->cajaAbierta();
        $datos['cajaAbierta'] = $abierta !== null ? $info($abierta)['codigo'] : null;

        return $datos;
    }

    /** @return array<string, mixed> JSON del cuerpo (la PWA envia application/json). */
    private function cuerpo(): array
    {
        try {
            $json = $this->request->getJSON(true);
        } catch (\CodeIgniter\HTTP\Exceptions\HTTPException) {
            throw new \Courier\Shared\Domain\Exception\ValidationException('Los datos enviados no son validos. Vuelva a escanear.');
        }

        return is_array($json) ? $json : (array) $this->request->getPost();
    }
}
