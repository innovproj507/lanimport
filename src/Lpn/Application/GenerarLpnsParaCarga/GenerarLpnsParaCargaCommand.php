<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\GenerarLpnsParaCarga;

use Courier\Shared\Application\CommandInterface;

final class GenerarLpnsParaCargaCommand implements CommandInterface
{
    public function __construct(
        public readonly string $cargaId,
        public readonly string $usuarioId,
    ) {
    }
}
