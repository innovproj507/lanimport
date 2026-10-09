<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ActualizarEstadoCarga;

use Courier\Shared\Application\CommandInterface;

final class ActualizarEstadoCargaCommand implements CommandInterface
{
    public function __construct(
        public readonly string $cargaId,
        public readonly string $nuevoEstado,
        public readonly string $usuarioId,
        public readonly ?string $comentario = null,
    ) {
    }
}
