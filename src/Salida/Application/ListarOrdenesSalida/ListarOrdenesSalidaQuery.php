<?php

declare(strict_types=1);

namespace Courier\Salida\Application\ListarOrdenesSalida;

use Courier\Shared\Application\QueryInterface;

final class ListarOrdenesSalidaQuery implements QueryInterface
{
    public function __construct(
        public readonly ?string $estado = null,
        public readonly ?string $clienteId = null,
    ) {
    }
}
