<?php

declare(strict_types=1);

namespace Courier\Auth\Infrastructure\Security;

use Courier\Auth\Domain\Usuario;

final class SessionManager
{
    public function start(Usuario $usuario, bool $recordar = false): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if ($recordar) {
                session_set_cookie_params(60 * 60 * 24 * 30);
            }

            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $usuario->id();
        $_SESSION['usuario_nombre'] = $usuario->nombre();
        $_SESSION['usuario_rol'] = $usuario->rol()->value;
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
        session_destroy();
    }

    public function currentUsuarioId(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['usuario_id'] ?? null;
    }

    public function currentRol(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['usuario_rol'] ?? null;
    }
}
