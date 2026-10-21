<?php

declare(strict_types=1);

namespace Courier\Salida\Application\DevolverCargaSalida;

use Courier\Shared\Application\CommandInterface;

final class DevolverCargaSalidaCommand implements CommandInterface
{
    /** @param string $grupo cargaId, o "caja-<lpnId>" de una caja reempacada (como en avancePorCarga) */
    public function __construct(
        public readonly string $ordenId,
        public readonly string $grupo,
    ) {
    }
}
