<?php

declare(strict_types=1);

namespace Courier\Carga\Application\SincronizarEstadoCarga;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\CargaStatus;
use Courier\Carga\Domain\ConteoBultosCargaInterface;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Notificacion\Application\NotificarDespachoCarga\NotificarDespachoCargaHandler;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Mueve el estado de la carga segun sus bultos fisicos (ver Carga::sincronizarConBultos):
 * lo llaman la recepcion de LPNs (escaneo en bodega) y la confirmacion de salidas.
 * Si la carga quedo despachada, avisa al cliente por correo.
 */
final class SincronizarEstadoCargaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly ConteoBultosCargaInterface $conteo,
        private readonly PdoHistorialEstadoRepository $historial,
        private readonly ClienteRepositoryInterface $clientes,
        private readonly NotificarDespachoCargaHandler $notificarDespacho,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return array<int, CargaStatus> estados nuevos alcanzados (vacio si no cambio) */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof SincronizarEstadoCargaCommand);

        $carga = $this->cargas->findById($command->cargaId);

        if ($carga === null) {
            return [];
        }

        $c = $this->conteo->contar($carga->id());
        $antes = count($carga->historial());
        $alcanzados = $carga->sincronizarConBultos($c['total'], $c['recibidos'], $c['despachados'], $command->usuarioId, $command->detalleDespacho);

        if ($alcanzados === []) {
            return [];
        }

        $this->unitOfWork->run(function () use ($carga, $antes) {
            $this->cargas->save($carga);

            foreach (array_slice($carga->historial(), $antes) as $entrada) {
                $this->historial->append($carga->id(), $entrada);
            }
        });

        if (in_array(CargaStatus::EN_TRANSITO, $alcanzados, true)) {
            $cliente = $this->clientes->findById($carga->clienteId());

            if ($cliente !== null) {
                $this->notificarDespacho->handle(
                    $carga->id(),
                    (string) $cliente->email(),
                    $cliente->nombreCompleto(),
                    (string) $carga->trackingNumero(),
                    $c['total'],
                    $command->datosEmbarque,
                );
            }
        }

        return $alcanzados;
    }
}
