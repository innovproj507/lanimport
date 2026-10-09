<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\ListarReempaqueOrdenes;

use Courier\Shared\Application\QueryInterface;

final class ListarReempaqueOrdenesQuery implements QueryInterface
{
    public function __construct(
        public readonly ?string $estado = null,
        public readonly ?string $clienteId = null,
    ) {
    }
}
