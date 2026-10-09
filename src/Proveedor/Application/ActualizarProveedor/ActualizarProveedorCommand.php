<?php

declare(strict_types=1);

namespace Courier\Proveedor\Application\ActualizarProveedor;

use Courier\Shared\Application\CommandInterface;

final class ActualizarProveedorCommand implements CommandInterface
{
    public function __construct(
        public readonly string $proveedorId,
        public readonly string $nombre,
    ) {
    }
}
