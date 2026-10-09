<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\DesactivarUbicacion;

use Courier\Shared\Application\CommandInterface;

final class DesactivarUbicacionCommand implements CommandInterface
{
    public function __construct(
        public readonly string $ubicacionId,
    ) {
    }
}
