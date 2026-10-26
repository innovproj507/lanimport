<?php

declare(strict_types=1);

namespace Courier\Salida\Application\AjustarBultosCargaSalida;

use Courier\Shared\Application\CommandInterface;

final class AjustarBultosCargaSalidaCommand implements CommandInterface
{
    /** @param array<int, string> $lpnIds los bultos de la carga que deben quedar en el DO */
    public function __construct(
        public readonly string $ordenId,
        public readonly string $cargaId,
        public readonly array $lpnIds,
    ) {
    }
}
