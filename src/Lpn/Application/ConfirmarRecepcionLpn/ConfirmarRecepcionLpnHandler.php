<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ConfirmarRecepcionLpn;

use Courier\Lpn\Domain\Lpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class ConfirmarRecepcionLpnHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly LpnRepositoryInterface $lpns,
        private readonly UbicacionRepositoryInterface $ubicaciones,
    ) {
    }

    public function handle(CommandInterface $command): Lpn
    {
        assert($command instanceof ConfirmarRecepcionLpnCommand);

        $lpn = $this->lpns->findByCodigo($command->codigoEscaneado);

        if ($lpn === null) {
            throw new NotFoundException("No se encontro ningun LPN con el codigo {$command->codigoEscaneado}.");
        }

        if ($command->ubicacionId !== null && $this->ubicaciones->findById($command->ubicacionId) === null) {
            throw new ValidationException('Seleccione una ubicacion valida.');
        }

        $lpn->confirmarRecepcion($command->ubicacionId);
        $this->lpns->save($lpn);

        return $lpn;
    }
}
