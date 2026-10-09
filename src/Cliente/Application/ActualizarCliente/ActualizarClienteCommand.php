<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\ActualizarCliente;

use Courier\Shared\Application\CommandInterface;

final class ActualizarClienteCommand implements CommandInterface
{
    public function __construct(
        public readonly string $clienteId,
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
