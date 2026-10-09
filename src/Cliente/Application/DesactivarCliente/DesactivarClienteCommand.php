<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\DesactivarCliente;

use Courier\Shared\Application\CommandInterface;

final class DesactivarClienteCommand implements CommandInterface
{
    public function __construct(
        public readonly string $clienteId,
    ) {
    }
}
