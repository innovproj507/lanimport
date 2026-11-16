<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\EliminarLpnsDeCarga;

use Courier\Shared\Application\CommandInterface;

final class EliminarLpnsDeCargaCommand implements CommandInterface
{
    public function __construct(
        public readonly string $cargaId,
        public readonly ?string $motivo = null,
        public readonly string $usuarioId = '',
    ) {
    }
}
