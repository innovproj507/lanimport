<?php

declare(strict_types=1);

namespace Courier\Carga\Application\EliminarCarga;

use Courier\Shared\Application\CommandInterface;

final class EliminarCargaCommand implements CommandInterface
{
    public function __construct(
        public readonly string $cargaId,
    ) {
    }
}
