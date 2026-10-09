<?php

declare(strict_types=1);

namespace Courier\Carga\Application\BuscarCargas;

use Courier\Shared\Application\QueryInterface;

final class BuscarCargasQuery implements QueryInterface
{
    public function __construct(
        public readonly string $termino,
        public readonly ?string $estado = null,
        public readonly int $page = 1,
        public readonly int $perPage = 10,
    ) {
    }
}
