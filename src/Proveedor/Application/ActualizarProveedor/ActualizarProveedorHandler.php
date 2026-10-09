<?php

declare(strict_types=1);

namespace Courier\Proveedor\Application\ActualizarProveedor;

use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

final class ActualizarProveedorHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ProveedorRepositoryInterface $proveedores)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarProveedorCommand);

        $proveedor = $this->proveedores->findById($command->proveedorId);

        if ($proveedor === null) {
            throw new NotFoundException("No se encontro el proveedor con id {$command->proveedorId}.");
        }

        $proveedor->actualizar($command->nombre);

        $this->proveedores->save($proveedor);

        return null;
    }
}
