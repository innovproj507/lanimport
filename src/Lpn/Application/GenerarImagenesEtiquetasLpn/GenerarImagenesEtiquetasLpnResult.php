<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\GenerarImagenesEtiquetasLpn;

final class GenerarImagenesEtiquetasLpnResult
{
    public function __construct(
        public readonly int $total,
        public readonly int $generadas,
        public readonly int $faltan,
    ) {
    }
}
