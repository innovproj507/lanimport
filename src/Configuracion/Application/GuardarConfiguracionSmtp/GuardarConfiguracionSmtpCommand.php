<?php

declare(strict_types=1);

namespace Courier\Configuracion\Application\GuardarConfiguracionSmtp;

use Courier\Shared\Application\CommandInterface;

final class GuardarConfiguracionSmtpCommand implements CommandInterface
{
    /**
     * @param string|null $passwordNueva null = conservar la contrasena guardada
     */
    public function __construct(
        public readonly string $host,
        public readonly int $puerto,
        public readonly ?string $usuario,
        public readonly ?string $passwordNueva,
        public readonly bool $borrarPassword,
        public readonly string $cifrado,
        public readonly string $remitenteEmail,
        public readonly string $remitenteNombre,
        public readonly string $usuarioId,
    ) {
    }
}
