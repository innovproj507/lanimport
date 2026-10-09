<?php

declare(strict_types=1);

namespace Courier\Factura\Application\VerFactura;

use Courier\Shared\Application\QueryInterface;

final class VerFacturaQuery implements QueryInterface
{
    public function __construct(public readonly string $facturaId)
    {
    }
}
