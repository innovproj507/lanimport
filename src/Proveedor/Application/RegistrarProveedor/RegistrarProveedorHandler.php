<?php

declare(strict_types=1);

namespace Courier\Proveedor\Application\RegistrarProveedor;

use Courier\Proveedor\Domain\Exception\ProveedorDuplicadoException;
use Courier\Proveedor\Domain\Proveedor;
use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\ValueObject\Uuid;

final class RegistrarProveedorHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ProveedorRepositoryInterface $proveedores)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RegistrarProveedorCommand);

        if ($this->proveedores->existsIdentificador($command->identificador)) {
            throw new ProveedorDuplicadoException("Ya existe un proveedor con el identificador {$command->identificador}.");
        }

        $proveedor = Proveedor::registrar(Uuid::generate(), $command->identificador, $command->nombre);
        $this->proveedores->save($proveedor);

        return $proveedor->id();
    }
}
