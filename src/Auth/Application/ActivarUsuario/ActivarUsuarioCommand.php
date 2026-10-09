<?php

declare(strict_types=1);

namespace Courier\Auth\Application\ActivarUsuario;

use Courier\Shared\Application\CommandInterface;

final class ActivarUsuarioCommand implements CommandInterface
{
    public function __construct(
        public readonly string $usuarioId,
    ) {
    }
}
