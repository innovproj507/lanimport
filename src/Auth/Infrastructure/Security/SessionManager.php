<?php

declare(strict_types=1);

namespace Courier\Auth\Infrastructure\Security;

use Courier\Auth\Domain\Usuario;

final class SessionManager
{
    /** La sesion ya se leyo y se libero: $_SESSION sigue disponible, pero no se vuelve a abrir. */
    private static bool $soloLectura = false;

    /**
     * PHP bloquea el archivo de sesion mientras dura la peticion, asi que dos peticiones del
     * mismo usuario (p.ej. la PDA escaneando mientras su pantalla consulta en vivo) van en fila.
     * Las consultas JSON que no escriben en la sesion la liberan apenas pasan el filtro auth.
     */
    public static function liberarParaLectura(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        self::$soloLectura = true;
    }

    public static function esSoloLectura(): bool
    {
        return self::$soloLectura;
    }

    private static function abrir(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !self::$soloLectura) {
            session_start();
        }
    }

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
        self::abrir();

        return $_SESSION['usuario_id'] ?? null;
    }

    public function currentRol(): ?string
    {
        self::abrir();

        return $_SESSION['usuario_rol'] ?? null;
    }
}
