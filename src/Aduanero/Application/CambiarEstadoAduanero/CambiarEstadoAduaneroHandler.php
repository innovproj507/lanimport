<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\CambiarEstadoAduanero;

use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

/** Activa o desactiva un aduanero (los inactivos no se ofrecen en cargas ni salidas nuevas). */
final class CambiarEstadoAduaneroHandler implements CommandHandlerInterface
{
    public function __construct(private readonly AduaneroRepositoryInterface $aduaneros)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CambiarEstadoAduaneroCommand);

        $aduanero = $this->aduaneros->findById($command->aduaneroId);

        if ($aduanero === null) {
            throw new NotFoundException('Aduanero no encontrado.');
        }

        $aduanero->cambiarEstado($command->activo);
        $this->aduaneros->save($aduanero);

        return null;
    }
}
