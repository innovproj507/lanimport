<?php

declare(strict_types=1);

namespace Courier\Salida\Application\CancelarOrdenSalida;

use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Salida\Domain\Exception\OrdenSalidaNotFoundException;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use Courier\Carga\Domain\BitacoraCargaInterface;

final class CancelarOrdenSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CancelarOrdenSalidaCommand);

        $orden = $this->ordenes->findById($command->salidaOrdenId);

        if ($orden === null) {
            throw new OrdenSalidaNotFoundException("No se encontro la orden de salida con id {$command->salidaOrdenId}.");
        }

        $orden->cancelar();

        $this->unitOfWork->run(function () use ($orden) {
            foreach ($orden->lineas() as $linea) {
                $lpn = $this->lpns->findById($linea->lpnId());

                if ($lpn !== null) {
                    $lpn->liberarReserva();
                    $this->lpns->save($lpn);
                }
            }

            // Cancelada no se guarda: se elimina para que no quede un DO vacio en el historial.
            $this->ordenes->eliminar($orden->id());
        });

        $this->bitacora?->registrarPorLpns(
            array_map(static fn ($l) => $l->lpnId(), $orden->lineas()),
            'orden_cancelada',
            "{$orden->etiquetaDo()} cancelada y eliminada: {n} bulto(s) regresan a bodega",
            $orden->id(),
        );

        return null;
    }
}
