<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ActualizarEtiquetaLpnConfig;

use Courier\Shared\Application\CommandInterface;

final class ActualizarEtiquetaLpnConfigCommand implements CommandInterface
{
    public function __construct(
        public readonly bool $mostrarMarca,
        public readonly bool $mostrarProveedor,
        public readonly bool $mostrarSerie,
        public readonly bool $mostrarDescripcion,
    ) {
    }
}
