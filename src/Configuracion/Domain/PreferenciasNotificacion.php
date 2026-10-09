<?php

declare(strict_types=1);

namespace Courier\Configuracion\Domain;

/**
 * Que correos automaticos envia el sistema. Los correos de aprobacion de
 * cliente y recuperacion de contrasena no son opcionales (llevan accesos).
 */
final class PreferenciasNotificacion
{
    public function __construct(
        public readonly bool $notificarIngresoCarga,
    ) {
    }

    public static function porDefecto(): self
    {
        return new self(true);
    }
}
