<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\BuscarAduaneros;

final class BuscarAduanerosResult
{
    /** @param array<int, \Courier\Aduanero\Domain\Aduanero> $items */
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
