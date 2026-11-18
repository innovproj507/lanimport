<?php

declare(strict_types=1);

namespace Courier\Reporte\Application\ObtenerFlujoCargas;

use Courier\Shared\Application\QueryInterface;

final class ObtenerFlujoCargasQuery implements QueryInterface
{
    /**
     * @param string $desde Y-m-d: periodo de "Salieron" (las otras etapas muestran lo de ahora)
     * @param string $hasta Y-m-d
     * @param array<int, string> $aduaneros identificadores (vacio = todos)
     * @param array<int, string> $clienteIds (vacio = todos)
     */
    public function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly array $aduaneros = [],
        public readonly array $clienteIds = [],
        /** @var array<int, string> maritimo|aereo|terrestre (vacio = todos) */
        public readonly array $tiposEnvio = [],
    ) {
    }
}
