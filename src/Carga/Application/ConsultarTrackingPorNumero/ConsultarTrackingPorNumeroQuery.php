<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ConsultarTrackingPorNumero;

use Courier\Shared\Application\QueryInterface;

final class ConsultarTrackingPorNumeroQuery implements QueryInterface
{
    /**
     * @param string|null $soloClienteId si viene (rol cliente), solo encuentra cargas de ese cliente
     * @param string|null $soloAduanero si viene (rol aduanero), solo cargas con ese aduanero (identificador)
     */
    public function __construct(
        public readonly string $trackingNumero,
        public readonly ?string $soloClienteId = null,
        public readonly ?string $soloAduanero = null,
    ) {
    }
}
