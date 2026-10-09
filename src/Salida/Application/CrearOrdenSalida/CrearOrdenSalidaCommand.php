<?php

declare(strict_types=1);

namespace Courier\Salida\Application\CrearOrdenSalida;

use Courier\Shared\Application\CommandInterface;

final class CrearOrdenSalidaCommand implements CommandInterface
{
    /** @param array<int, string> $lpnIds */
    public function __construct(
        public readonly string $clienteId,
        public readonly array $lpnIds,
        public readonly ?string $aduaneroIdentificador,
        public readonly string $usuarioId,
    ) {
    }
}
