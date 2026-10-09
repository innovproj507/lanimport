<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\CompletarReempaque;

use Courier\Shared\Application\CommandInterface;

final class CompletarReempaqueCommand implements CommandInterface
{
    public function __construct(
        public readonly string $reempaqueOrdenId,
        public readonly int $cantidadDestinos,
        public readonly ?string $ubicacionId,
        public readonly string $usuarioId,
    ) {
    }
}
