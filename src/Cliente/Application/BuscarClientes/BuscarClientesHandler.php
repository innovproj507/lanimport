<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\BuscarClientes;

use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class BuscarClientesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ClienteRepositoryInterface $clientes)
    {
    }

    public function handle(QueryInterface $query): BuscarClientesResult
    {
        assert($query instanceof BuscarClientesQuery);

        $resultado = $this->clientes->searchPaginado($query->termino, $query->page, $query->perPage);

        return new BuscarClientesResult($resultado['items'], $resultado['total'], $query->page, $query->perPage);
    }
}
