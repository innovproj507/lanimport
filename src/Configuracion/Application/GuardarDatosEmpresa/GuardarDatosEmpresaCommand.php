<?php

declare(strict_types=1);

namespace Courier\Configuracion\Application\GuardarDatosEmpresa;

use Courier\Shared\Application\CommandInterface;

final class GuardarDatosEmpresaCommand implements CommandInterface
{
    public function __construct(
        public readonly string $nombre,
        public readonly ?string $ruc,
        public readonly ?string $dv,
        public readonly ?string $claveOperaciones,
        public readonly ?string $direccion,
        public readonly ?string $telefono,
        public readonly ?string $email,
        public readonly string $usuarioId,
    ) {
    }
}
