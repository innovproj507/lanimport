<?php

declare(strict_types=1);

namespace Courier\Pais\Application\RegistrarPais;

use Courier\Shared\Application\CommandInterface;

final class RegistrarPaisCommand implements CommandInterface
{
    public function __construct(
        public readonly string $identificador,
        public readonly string $nombre,
        public readonly bool $mostrarSerieEtiqueta = false,
    ) {
    }
}
