<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ActualizarUbicacion;

use Courier\Shared\Application\CommandInterface;

final class ActualizarUbicacionCommand implements CommandInterface
{
    public function __construct(
        public readonly string $ubicacionId,
        public readonly ?string $zona,
        public readonly ?string $pasillo,
        public readonly ?string $nivel,
    ) {
    }
}
