<?php

declare(strict_types=1);

namespace Courier\Bodega\Application\GuardarBodega;

use Courier\Bodega\Domain\Bodega;
use Courier\Bodega\Domain\BodegaRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;

/** Crea o edita una bodega (Configuracion > Bodegas). Devuelve su id. */
final class GuardarBodegaHandler implements CommandHandlerInterface
{
    public function __construct(private readonly BodegaRepositoryInterface $bodegas)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof GuardarBodegaCommand);

        if ($this->bodegas->existsCodigo($command->codigo, $command->id)) {
            throw new ValidationException('Ya existe otra bodega con el codigo ' . strtoupper(trim($command->codigo)) . '.');
        }

        if ($command->id === null) {
            $bodega = Bodega::registrar((string) Uuid::generate(), $command->codigo, $command->nombre, $command->direccion);
        } else {
            $bodega = $this->bodegas->findById($command->id) ?? throw new NotFoundException('Bodega no encontrada.');
            $bodega->actualizar($command->codigo, $command->nombre, $command->direccion, $command->activo);
        }

        $this->bodegas->save($bodega);

        return $bodega->id();
    }
}
