<?php

declare(strict_types=1);

namespace Courier\Naviera\Application\BuscarNavieras;

use Courier\Shared\Application\QueryInterface;

final class BuscarNavierasQuery implements QueryInterface
{
    public function __construct(
        public readonly string $termino,
        public readonly int $page = 1,
        public readonly int $perPage = 10,
    ) {
    }
}
