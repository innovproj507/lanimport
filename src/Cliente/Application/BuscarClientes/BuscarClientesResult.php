<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\BuscarClientes;

final class BuscarClientesResult
{
    /** @param array<int, \Courier\Cliente\Domain\Cliente> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
