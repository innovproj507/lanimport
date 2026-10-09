<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\RegistrarUbicacion;

use Courier\Lpn\Domain\Exception\UbicacionDuplicadaException;
use Courier\Lpn\Domain\Ubicacion;
use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\ValueObject\Uuid;

final class RegistrarUbicacionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UbicacionRepositoryInterface $ubicaciones)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RegistrarUbicacionCommand);

        if ($this->ubicaciones->existsCodigo($command->codigo)) {
            throw new UbicacionDuplicadaException("Ya existe una ubicacion con el codigo {$command->codigo}.");
        }

        $ubicacion = Ubicacion::registrar(
            Uuid::generate(),
            $command->codigo,
            $command->zona,
            $command->pasillo,
            $command->nivel,
        );

        $this->ubicaciones->save($ubicacion);

        return $ubicacion->id();
    }
}
