<?php

declare(strict_types=1);

namespace Courier\Configuracion\Application\GuardarPreferenciasNotificacion;

use Courier\Shared\Application\CommandInterface;

final class GuardarPreferenciasNotificacionCommand implements CommandInterface
{
    public function __construct(
        public readonly bool $notificarIngresoCarga,
        public readonly string $usuarioId,
    ) {
    }
}
