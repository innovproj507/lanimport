<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ActualizarUbicacion;

use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

final class ActualizarUbicacionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UbicacionRepositoryInterface $ubicaciones)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarUbicacionCommand);

        $ubicacion = $this->ubicaciones->findById($command->ubicacionId);

        if ($ubicacion === null) {
            throw new NotFoundException("No se encontro la ubicacion con id {$command->ubicacionId}.");
        }

        $ubicacion->actualizar($command->zona, $command->pasillo, $command->nivel);

        $this->ubicaciones->save($ubicacion);

        return null;
    }
}
