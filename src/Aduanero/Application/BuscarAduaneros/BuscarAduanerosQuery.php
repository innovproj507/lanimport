<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\BuscarAduaneros;

use Courier\Shared\Application\QueryInterface;

final class BuscarAduanerosQuery implements QueryInterface
{
    public function __construct(
        public readonly string $termino,
        public readonly int $page = 1,
        public readonly int $perPage = 10,
    ) {
    }
}
