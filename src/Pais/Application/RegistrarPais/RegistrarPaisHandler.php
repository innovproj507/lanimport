<?php

declare(strict_types=1);

namespace Courier\Pais\Application\RegistrarPais;

use Courier\Pais\Domain\Exception\PaisDuplicadoException;
use Courier\Pais\Domain\Pais;
use Courier\Pais\Domain\PaisRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\ValueObject\Uuid;

final class RegistrarPaisHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PaisRepositoryInterface $paises)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RegistrarPaisCommand);

        if ($this->paises->existsIdentificador($command->identificador)) {
            throw new PaisDuplicadoException("Ya existe un pais con el identificador {$command->identificador}.");
        }

        $pais = Pais::registrar(Uuid::generate(), $command->identificador, $command->nombre, $command->mostrarSerieEtiqueta);
        $this->paises->save($pais);

        return $pais->id();
    }
}
