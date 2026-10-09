<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\RegistrarCliente;

use Courier\Shared\Application\CommandInterface;

final class RegistrarClienteCommand implements CommandInterface
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $email,
        public readonly ?string $telefono,
        public readonly ?string $direccion,
        public readonly ?string $apellido = null,
        public readonly ?string $empresa = null,
        public readonly ?string $pais = null,
    ) {
    }
}
