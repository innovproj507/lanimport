<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\GenerarImagenesEtiquetasLpn;

use Courier\Shared\Application\CommandInterface;

final class GenerarImagenesEtiquetasLpnCommand implements CommandInterface
{
    /**
     * @param float $segundosMax tiempo maximo de esta tanda; 0 = no genera, solo cuenta las que faltan.
     */
    public function __construct(
        public readonly string $cargaId,
        public readonly float $segundosMax,
    ) {
    }
}
