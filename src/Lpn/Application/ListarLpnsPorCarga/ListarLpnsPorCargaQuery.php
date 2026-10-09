<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ListarLpnsPorCarga;

use Courier\Shared\Application\QueryInterface;

final class ListarLpnsPorCargaQuery implements QueryInterface
{
    public function __construct(
        public readonly string $cargaId,
    ) {
    }
}
