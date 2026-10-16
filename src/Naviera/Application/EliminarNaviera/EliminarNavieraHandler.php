<?php

declare(strict_types=1);

namespace Courier\Naviera\Application\EliminarNaviera;

use Courier\Naviera\Domain\NavieraRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\RegistroEnUsoException;

/** Elimina una naviera que no se uso en ninguna salida; si se uso, se debe desactivar. */
final class EliminarNavieraHandler implements CommandHandlerInterface
{
    public function __construct(private readonly NavieraRepositoryInterface $navieras)
    {
    }

    /** @return string nombre de la naviera eliminada */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EliminarNavieraCommand);

        $naviera = $this->navieras->findById($command->navieraId);

        if ($naviera === null) {
            throw new NotFoundException('Naviera no encontrada.');
        }

        $referencias = $this->navieras->referencias($naviera->id());

        if ($referencias !== []) {
            throw RegistroEnUsoException::para("la naviera {$naviera->nombre()}", $referencias);
        }

        $this->navieras->delete($naviera->id());

        return $naviera->nombre();
    }
}
