<?php

declare(strict_types=1);

namespace Courier\Proveedor\Application\BuscarProveedores;

use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class BuscarProveedoresHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ProveedorRepositoryInterface $proveedores)
    {
    }

    public function handle(QueryInterface $query): BuscarProveedoresResult
    {
        assert($query instanceof BuscarProveedoresQuery);

        $resultado = $this->proveedores->searchPaginado($query->termino, $query->page, $query->perPage);

        return new BuscarProveedoresResult($resultado['items'], $resultado['total'], $query->page, $query->perPage);
    }
}
