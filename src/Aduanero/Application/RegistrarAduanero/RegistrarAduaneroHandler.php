<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\RegistrarAduanero;

use Courier\Aduanero\Domain\Aduanero;
use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;

final class RegistrarAduaneroHandler implements CommandHandlerInterface
{
    public function __construct(private readonly AduaneroRepositoryInterface $aduaneros)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RegistrarAduaneroCommand);

        if ($command->pais === null || trim($command->pais) === '') {
            throw new ValidationException('Debe seleccionar el pais del aduanero.');
        }

        $aduanero = Aduanero::registrar(Uuid::generate(), $command->nombre, $command->pais);
        $this->aduaneros->save($aduanero);

        return $aduanero->id();
    }
}
