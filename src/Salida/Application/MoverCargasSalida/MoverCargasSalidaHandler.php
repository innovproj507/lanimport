<?php

declare(strict_types=1);

namespace Courier\Salida\Application\MoverCargasSalida;

use Courier\Carga\Application\SincronizarEstadoCarga\SincronizarEstadoCargaCommand;
use Courier\Carga\Application\SincronizarEstadoCarga\SincronizarEstadoCargaHandler;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Salida\Domain\EstadoSalidaOrden;
use Courier\Salida\Domain\SalidaOrden;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Pasa cargas de una salida pendiente a otra (o a un DO nuevo), conservando lo que bodega ya
 * escaneo: unir DOs que van en el mismo contenedor, o sacar una carga que se unio por error.
 * Las fotos de las cargas movidas pasan con ellas. Si la salida de origen se queda sin cargas, se elimina.
 * A una salida ya confirmada solo pasan bultos escaneados, y quedan despachados como al finalizar.
 */
final class MoverCargasSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
        private readonly SincronizarEstadoCargaHandler $sincronizarCarga,
    ) {
    }

    /** @return SalidaOrden la salida destino (ya guardada, con su DO) */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MoverCargasSalidaCommand);

        $origen = $this->ordenes->findById($command->origenId) ?? throw new NotFoundException('Orden de salida no encontrada.');

        if ($command->destinoId === $origen->id()) {
            throw new ValidationException('Elija otra salida: es la misma.');
        }

        $destino = null;

        if ($command->destinoId !== null) {
            $destino = $this->ordenes->findById($command->destinoId) ?? throw new NotFoundException('La salida destino no existe.');
        }

        $lineas = $origen->sacarLineas($this->lpnIds($origen, $command->grupos));
        $clienteIds = [];
        $lpnsMovidos = [];

        foreach ($lineas as $linea) {
            $lpn = $this->lpns->findById($linea->lpnId());

            if ($lpn !== null) {
                $lpnsMovidos[] = $lpn;
                $clienteIds[] = (string) $lpn->clienteId();
            }
        }

        $clienteIds = array_values(array_unique($clienteIds));

        if ($destino === null) {
            $destino = SalidaOrden::crear(Uuid::generate(), count($clienteIds) === 1 ? $clienteIds[0] : null, null, $lineas, $command->usuarioId);
        } else {
            $destino->recibirLineas($lineas, $clienteIds);
        }

        $despachar = $destino->estado() === EstadoSalidaOrden::CONFIRMADA;
        $cargaIds = [];

        $this->unitOfWork->run(function () use ($origen, $destino, $despachar, $lpnsMovidos, &$cargaIds): void {
            $this->ordenes->save($destino);
            $this->ordenes->save($origen);
            $this->ordenes->pasarFotosDeCargasMovidas($origen->id(), $destino->id());

            if ($origen->lineas() === []) {
                $this->ordenes->eliminar($origen->id());
            }

            if ($despachar) {
                foreach ($lpnsMovidos as $lpn) {
                    $lpn->confirmarSalida();
                    $this->lpns->save($lpn);

                    foreach ($lpn->cargaId() !== null ? [$lpn->cargaId()] : $this->lpns->cargasContenidas($lpn->id()) as $cargaId) {
                        $cargaIds[$cargaId] = true;
                    }
                }
            }
        });

        $destino = $this->ordenes->findById($destino->id());

        // Unidas a una salida ya despachada: sus cargas completas pasan a "En transito" (y se avisa al cliente).
        if ($despachar && $destino !== null) {
            $contenedor = $destino->embarque()->contenedorFormateado();
            $detalle = $destino->etiquetaDo() . ($contenedor !== null ? ", contenedor {$contenedor}" : '');

            foreach (array_keys($cargaIds) as $cargaId) {
                $this->sincronizarCarga->handle(new SincronizarEstadoCargaCommand($cargaId, $command->usuarioId, $detalle, $destino->datosParaCliente()));
            }
        }

        return $destino;
    }

    /**
     * @param array<int, string>|null $grupos
     * @return array<int, string>
     */
    private function lpnIds(SalidaOrden $origen, ?array $grupos): array
    {
        if ($grupos === null) {
            return array_map(static fn ($l) => $l->lpnId(), $origen->lineas());
        }

        $porCodigo = [];
        foreach ($origen->lineas() as $linea) {
            $porCodigo[$linea->codigoLpn()] = $linea->lpnId();
        }

        $ids = [];
        foreach ($this->ordenes->avancePorCarga($origen->id()) as $grupo) {
            if (in_array($grupo['cargaId'], $grupos, true)) {
                foreach ($grupo['codigos'] as $codigo) {
                    if (isset($porCodigo[$codigo])) {
                        $ids[] = $porCodigo[$codigo];
                    }
                }
            }
        }

        if ($ids === []) {
            throw new ValidationException('Esa carga no esta en esta salida.');
        }

        return $ids;
    }
}
