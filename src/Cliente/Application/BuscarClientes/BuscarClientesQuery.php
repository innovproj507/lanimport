<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\BuscarClientes;

use Courier\Shared\Application\QueryInterface;

final class BuscarClientesQuery implements QueryInterface
{
    public function __construct(
        public readonly string $termino,
        public readonly int $page = 1,
        public readonly int $perPage = 10,
    ) {
    }
}
