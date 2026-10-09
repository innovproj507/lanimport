<?php

declare(strict_types=1);

namespace Courier\Carga\Application\VincularActaRecepcion;

use Courier\Shared\Application\CommandInterface;

final class VincularActaRecepcionCommand implements CommandInterface
{
    public function __construct(
        public readonly string $actaId,
        public readonly string $cargaId,
    ) {
    }
}
