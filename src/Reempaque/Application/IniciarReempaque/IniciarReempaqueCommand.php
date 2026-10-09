<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\IniciarReempaque;

use Courier\Shared\Application\CommandInterface;

final class IniciarReempaqueCommand implements CommandInterface
{
    /** @param array<int, string> $origenLpnIds */
    public function __construct(
        public readonly string $tipo,
        public readonly string $clienteId,
        public readonly array $origenLpnIds,
        public readonly string $usuarioId,
    ) {
    }
}
