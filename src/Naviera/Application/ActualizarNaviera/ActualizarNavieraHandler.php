<?php

declare(strict_types=1);

namespace Courier\Naviera\Application\ActualizarNaviera;

use Courier\Naviera\Domain\Exception\NavieraDuplicadaException;
use Courier\Naviera\Domain\NavieraRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

final class ActualizarNavieraHandler implements CommandHandlerInterface
{
    public function __construct(private readonly NavieraRepositoryInterface $navieras)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarNavieraCommand);

        $naviera = $this->navieras->findById($command->navieraId);

        if ($naviera === null) {
            throw new NotFoundException('Naviera no encontrada.');
        }

        $naviera->actualizar($command->nombre, $command->activo);

        if ($this->navieras->existsNombre($naviera->nombre(), $naviera->id())) {
            throw new NavieraDuplicadaException("Ya existe otra naviera llamada {$naviera->nombre()}.");
        }

        $this->navieras->save($naviera);

        return null;
    }
}
