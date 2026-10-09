<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\ActualizarAduanero;

use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class ActualizarAduaneroHandler implements CommandHandlerInterface
{
    public function __construct(private readonly AduaneroRepositoryInterface $aduaneros)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarAduaneroCommand);

        $aduanero = $this->aduaneros->findById($command->aduaneroId);

        if ($aduanero === null) {
            throw new NotFoundException("No se encontro el aduanero con id {$command->aduaneroId}.");
        }

        if ($command->pais === null || trim($command->pais) === '') {
            throw new ValidationException('Debe seleccionar el pais del aduanero.');
        }

        $aduanero->actualizar($command->nombre, $command->pais);

        $this->aduaneros->save($aduanero);

        return null;
    }
}
