<?php

declare(strict_types=1);

namespace Courier\Naviera\Application\RegistrarNaviera;

use Courier\Naviera\Domain\Exception\NavieraDuplicadaException;
use Courier\Naviera\Domain\Naviera;
use Courier\Naviera\Domain\NavieraRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\ValueObject\Uuid;

final class RegistrarNavieraHandler implements CommandHandlerInterface
{
    public function __construct(private readonly NavieraRepositoryInterface $navieras)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RegistrarNavieraCommand);

        $naviera = Naviera::registrar(Uuid::generate(), $command->nombre);

        if ($this->navieras->existsNombre($naviera->nombre())) {
            throw new NavieraDuplicadaException("Ya existe la naviera {$naviera->nombre()}.");
        }

        $this->navieras->save($naviera);

        return $naviera->id();
    }
}
