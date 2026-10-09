<?php

declare(strict_types=1);

namespace Courier\Proveedor\Application\ListarProveedores;

use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ListarProveedoresHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ProveedorRepositoryInterface $proveedores)
    {
    }

    /** @return array<int, \Courier\Proveedor\Domain\Proveedor> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarProveedoresQuery);

        return $this->proveedores->findAll();
    }
}
