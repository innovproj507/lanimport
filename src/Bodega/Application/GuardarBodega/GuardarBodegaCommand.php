<?php

declare(strict_types=1);

namespace Courier\Bodega\Application\GuardarBodega;

use Courier\Shared\Application\CommandInterface;

final class GuardarBodegaCommand implements CommandInterface
{
    /** @param string|null $id null = bodega nueva */
    public function __construct(
        public readonly ?string $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $direccion,
        public readonly bool $activo = true,
    ) {
    }
}
