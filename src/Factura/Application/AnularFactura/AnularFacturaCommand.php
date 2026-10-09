<?php

declare(strict_types=1);

namespace Courier\Factura\Application\AnularFactura;

use Courier\Shared\Application\CommandInterface;

final class AnularFacturaCommand implements CommandInterface
{
    public function __construct(public readonly string $facturaId)
    {
    }
}
