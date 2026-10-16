<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\EliminarCliente;

use Courier\Shared\Application\CommandInterface;

final class EliminarClienteCommand implements CommandInterface
{
    public function __construct(
        public readonly string $clienteId,
    ) {
    }
}
