<?php

declare(strict_types=1);

namespace Courier\Auth\Application\Login;

final class LoginResult
{
    public function __construct(
        public readonly string $usuarioId,
        public readonly string $nombre,
        public readonly string $rol,
        public readonly ?string $accessToken = null,
        public readonly ?string $refreshToken = null,
        public readonly ?int $expiresIn = null,
    ) {
    }
}
