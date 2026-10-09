<?php

declare(strict_types=1);

namespace Courier\Factura\Application\ListarFacturas;

use Courier\Shared\Application\QueryInterface;

final class ListarFacturasQuery implements QueryInterface
{
    public function __construct(
        public readonly ?string $estado = null,
        public readonly ?string $clienteId = null,
    ) {
    }
}
