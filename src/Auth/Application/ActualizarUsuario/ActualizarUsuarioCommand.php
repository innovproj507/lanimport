<?php

declare(strict_types=1);

namespace Courier\Auth\Application\ActualizarUsuario;

use Courier\Shared\Application\CommandInterface;

final class ActualizarUsuarioCommand implements CommandInterface
{
    public function __construct(
        public readonly string $usuarioId,
        public readonly string $nombre,
        public readonly string $rol,
        public readonly ?string $nuevaPassword = null,
    ) {
    }
}
