<?php

declare(strict_types=1);

namespace Courier\Proveedor\Application\BuscarProveedores;

use Courier\Shared\Application\QueryInterface;

final class BuscarProveedoresQuery implements QueryInterface
{
    public function __construct(
        public readonly string $termino,
        public readonly int $page = 1,
        public readonly int $perPage = 10,
    ) {
    }
}
