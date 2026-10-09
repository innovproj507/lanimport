<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ActivarUbicacion;

use Courier\Shared\Application\CommandInterface;

final class ActivarUbicacionCommand implements CommandInterface
{
    public function __construct(
        public readonly string $ubicacionId,
    ) {
    }
}
