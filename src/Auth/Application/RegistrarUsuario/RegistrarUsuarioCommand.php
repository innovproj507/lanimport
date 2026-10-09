<?php

declare(strict_types=1);

namespace Courier\Auth\Application\RegistrarUsuario;

use Courier\Shared\Application\CommandInterface;

final class RegistrarUsuarioCommand implements CommandInterface
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $email,
        public readonly string $password,
        public readonly string $rol,
    ) {
    }
}
