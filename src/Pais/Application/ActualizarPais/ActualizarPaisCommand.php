<?php

declare(strict_types=1);

namespace Courier\Pais\Application\ActualizarPais;

use Courier\Shared\Application\CommandInterface;

final class ActualizarPaisCommand implements CommandInterface
{
    public function __construct(
        public readonly string $paisId,
        public readonly string $nombre,
        public readonly bool $mostrarSerieEtiqueta,
    ) {
    }
}
