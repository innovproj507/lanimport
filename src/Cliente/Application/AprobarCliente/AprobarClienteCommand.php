<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\AprobarCliente;

use Courier\Shared\Application\CommandInterface;

final class AprobarClienteCommand implements CommandInterface
{
    public function __construct(
        public readonly string $clienteId,
    ) {
    }
}
