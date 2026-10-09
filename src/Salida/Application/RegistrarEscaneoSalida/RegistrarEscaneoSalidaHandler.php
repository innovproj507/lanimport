<?php

declare(strict_types=1);

namespace Courier\Salida\Application\RegistrarEscaneoSalida;

use Courier\Salida\Domain\Exception\OrdenSalidaNotFoundException;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;

final class RegistrarEscaneoSalidaHandler implements CommandHandlerInterface
{
    public function __construct(private readonly SalidaOrdenRepositoryInterface $ordenes)
    {
    }

    /** @return array{escaneadas: int, total: int} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof RegistrarEscaneoSalidaCommand);

        $orden = $this->ordenes->findById($command->salidaOrdenId);

        if ($orden === null) {
            throw new OrdenSalidaNotFoundException("No se encontro la orden de salida con id {$command->salidaOrdenId}.");
        }

        $orden->registrarEscaneo($command->codigoEscaneado);
        $this->ordenes->save($orden);

        return $orden->progreso();
    }
}
