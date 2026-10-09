<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ConfirmarRecepcionLpn;

use Courier\Shared\Application\CommandInterface;

final class ConfirmarRecepcionLpnCommand implements CommandInterface
{
    public function __construct(
        public readonly string $codigoEscaneado,
        public readonly ?string $ubicacionId,
        public readonly string $usuarioId,
    ) {
    }
}
