<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\ReactivarCliente;

use Courier\Shared\Application\CommandInterface;

final class ReactivarClienteCommand implements CommandInterface
{
    public function __construct(
        public readonly string $clienteId,
    ) {
    }
}
