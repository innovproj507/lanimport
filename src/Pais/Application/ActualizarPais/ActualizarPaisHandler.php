<?php

declare(strict_types=1);

namespace Courier\Pais\Application\ActualizarPais;

use Courier\Pais\Domain\PaisRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

final class ActualizarPaisHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PaisRepositoryInterface $paises)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarPaisCommand);

        $pais = $this->paises->findById($command->paisId);

        if ($pais === null) {
            throw new NotFoundException("No se encontro el pais con id {$command->paisId}.");
        }

        $pais->actualizar($command->nombre, $command->mostrarSerieEtiqueta);

        $this->paises->save($pais);

        return null;
    }
}
