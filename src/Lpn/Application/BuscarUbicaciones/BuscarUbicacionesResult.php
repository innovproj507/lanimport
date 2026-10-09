<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\BuscarUbicaciones;

final class BuscarUbicacionesResult
{
    /** @param array<int, \Courier\Lpn\Domain\Ubicacion> $items */
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
