<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\ListarClientes;

use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ListarClientesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ClienteRepositoryInterface $clientes)
    {
    }

    /** @return array<int, \Courier\Cliente\Domain\Cliente> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarClientesQuery);

        return $this->clientes->findApproved();
    }
}
