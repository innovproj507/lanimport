<?php

declare(strict_types=1);

namespace Courier\Factura\Application\MarcarFacturaPagada;

use Courier\Shared\Application\CommandInterface;

final class MarcarFacturaPagadaCommand implements CommandInterface
{
    public function __construct(public readonly string $facturaId)
    {
    }
}
