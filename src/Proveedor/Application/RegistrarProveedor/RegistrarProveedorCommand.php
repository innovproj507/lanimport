<?php

declare(strict_types=1);

namespace Courier\Proveedor\Application\RegistrarProveedor;

use Courier\Shared\Application\CommandInterface;

final class RegistrarProveedorCommand implements CommandInterface
{
    public function __construct(
        public readonly string $identificador,
        public readonly string $nombre,
    ) {
    }
}
