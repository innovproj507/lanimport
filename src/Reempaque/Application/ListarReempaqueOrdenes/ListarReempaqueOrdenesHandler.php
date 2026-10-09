<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\ListarReempaqueOrdenes;

use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ListarReempaqueOrdenesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ReempaqueOrdenRepositoryInterface $ordenes)
    {
    }

    /** @return array<int, \Courier\Reempaque\Domain\ReempaqueOrden> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarReempaqueOrdenesQuery);

        return $this->ordenes->search([
            'estado' => $query->estado,
            'clienteId' => $query->clienteId,
        ]);
    }
}
