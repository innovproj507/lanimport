<?php

declare(strict_types=1);

namespace Courier\Salida\Application\ConfirmarSalida;

use Courier\Shared\Application\CommandInterface;

final class ConfirmarSalidaCommand implements CommandInterface
{
    public function __construct(
        public readonly string $salidaOrdenId,
        public readonly string $usuarioId,
    ) {
    }
}
