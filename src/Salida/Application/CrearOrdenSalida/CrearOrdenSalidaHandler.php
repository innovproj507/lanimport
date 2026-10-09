<?php

declare(strict_types=1);

namespace Courier\Salida\Application\CrearOrdenSalida;

use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Salida\Domain\SalidaLinea;
use Courier\Salida\Domain\SalidaOrden;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class CrearOrdenSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly LpnRepositoryInterface $lpns,
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CrearOrdenSalidaCommand);

        if ($command->lpnIds === []) {
            throw new ValidationException('Debe seleccionar al menos un LPN para crear la orden de salida.');
        }

        $lpnsSeleccionados = [];

        foreach ($command->lpnIds as $lpnId) {
            $lpn = $this->lpns->findById($lpnId);

            if ($lpn === null) {
                throw new ValidationException("No se encontro el LPN con id {$lpnId}.");
            }

            if ($lpn->estado() !== EstadoLpn::AVAILABLE) {
                throw new ValidationException("El LPN {$lpn->codigo()} no esta disponible.");
            }

            if ($lpn->clienteId() !== $command->clienteId) {
                throw new ValidationException("El LPN {$lpn->codigo()} no pertenece al cliente seleccionado.");
            }

            $lpnsSeleccionados[] = $lpn;
        }

        return $this->unitOfWork->run(function () use ($command, $lpnsSeleccionados) {
            $lineas = array_map(
                fn ($lpn) => new SalidaLinea($lpn->id(), (string) $lpn->codigo()),
                $lpnsSeleccionados,
            );

            $orden = SalidaOrden::crear(
                Uuid::generate(),
                $command->clienteId,
                $command->aduaneroIdentificador,
                $lineas,
                $command->usuarioId,
            );

            $this->ordenes->save($orden);

            foreach ($lpnsSeleccionados as $lpn) {
                $lpn->reservarParaSalida();
                $this->lpns->save($lpn);
            }

            return $orden->id();
        });
    }
}
