<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ListarCargasPorCliente;

use Courier\Shared\Application\QueryInterface;

final class ListarCargasPorClienteQuery implements QueryInterface
{
    public function __construct(
        public readonly string $clienteId,
    ) {
    }
}
