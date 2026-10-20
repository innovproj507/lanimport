<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\EmpacarBultoReempaque;

use Courier\Shared\Application\CommandInterface;

final class EmpacarBultoReempaqueCommand implements CommandInterface
{
    /** @param bool $quitar true = sacarlo de la caja abierta (escaneado por error) */
    public function __construct(
        public readonly string $reempaqueOrdenId,
        public readonly string $codigoEscaneado,
        public readonly bool $quitar = false,
    ) {
    }
}
