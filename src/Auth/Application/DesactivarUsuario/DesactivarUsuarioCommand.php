<?php

declare(strict_types=1);

namespace Courier\Auth\Application\DesactivarUsuario;

use Courier\Shared\Application\CommandInterface;

final class DesactivarUsuarioCommand implements CommandInterface
{
    public function __construct(
        public readonly string $usuarioId,
    ) {
    }
}
