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
        // Desde la pantalla de una carga: el LPN debe ser de esa carga. null = escaneo general de bodega.
        public readonly ?string $cargaId = null,
    ) {
    }
}
