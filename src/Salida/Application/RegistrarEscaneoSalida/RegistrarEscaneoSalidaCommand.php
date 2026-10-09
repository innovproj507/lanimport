<?php

declare(strict_types=1);

namespace Courier\Salida\Application\RegistrarEscaneoSalida;

use Courier\Shared\Application\CommandInterface;

final class RegistrarEscaneoSalidaCommand implements CommandInterface
{
    public function __construct(
        public readonly string $salidaOrdenId,
        public readonly string $codigoEscaneado,
    ) {
    }
}
