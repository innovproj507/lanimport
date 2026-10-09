<?php

declare(strict_types=1);

namespace Courier\Factura\Application\ListarFacturas;

use Courier\Factura\Domain\FacturaRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ListarFacturasHandler implements QueryHandlerInterface
{
    public function __construct(private readonly FacturaRepositoryInterface $facturas)
    {
    }

    /** @return array<int, \Courier\Factura\Domain\Factura> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarFacturasQuery);

        return $this->facturas->search([
            'estado' => $query->estado,
            'clienteId' => $query->clienteId,
        ]);
    }
}
