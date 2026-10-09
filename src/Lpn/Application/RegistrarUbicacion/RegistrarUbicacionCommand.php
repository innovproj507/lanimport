<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\RegistrarUbicacion;

use Courier\Shared\Application\CommandInterface;

final class RegistrarUbicacionCommand implements CommandInterface
{
    public function __construct(
        public readonly string $codigo,
        public readonly ?string $zona,
        public readonly ?string $pasillo,
        public readonly ?string $nivel,
    ) {
    }
}
