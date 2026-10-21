<?php

declare(strict_types=1);

namespace Courier\Salida\Application\AgregarCargasSalida;

use Courier\Shared\Application\CommandInterface;

final class AgregarCargasSalidaCommand implements CommandInterface
{
    /** @param array<int, string> $lpnIds LPNs listos de las cargas (o cajas reempacadas) que se agregan */
    public function __construct(
        public readonly string $ordenId,
        public readonly array $lpnIds,
    ) {
    }
}
