<?php

declare(strict_types=1);

namespace Courier\Salida\Application\ActualizarEmbarqueSalida;

use Courier\Shared\Application\CommandInterface;

final class ActualizarEmbarqueSalidaCommand implements CommandInterface
{
    public function __construct(
        public readonly string $ordenId,
        public readonly ?string $numeroContenedor,
        public readonly ?string $numeroSello,
        public readonly ?string $navieraId,
        public readonly ?string $nombreBarco,
        public readonly ?string $fechaCargue,
        public readonly ?string $fechaSalidaEtd,
        public readonly ?string $destinoFinal,
    ) {
    }
}
