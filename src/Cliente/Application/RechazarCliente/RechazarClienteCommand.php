<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\RechazarCliente;

use Courier\Shared\Application\CommandInterface;

final class RechazarClienteCommand implements CommandInterface
{
    public function __construct(
        public readonly string $clienteId,
    ) {
    }
}
