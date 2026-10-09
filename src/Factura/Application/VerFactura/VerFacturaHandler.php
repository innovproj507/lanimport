<?php

declare(strict_types=1);

namespace Courier\Factura\Application\VerFactura;

use Courier\Factura\Domain\Factura;
use Courier\Factura\Domain\FacturaRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class VerFacturaHandler implements QueryHandlerInterface
{
    public function __construct(private readonly FacturaRepositoryInterface $facturas)
    {
    }

    public function handle(QueryInterface $query): ?Factura
    {
        assert($query instanceof VerFacturaQuery);

        return $this->facturas->findById($query->facturaId);
    }
}
