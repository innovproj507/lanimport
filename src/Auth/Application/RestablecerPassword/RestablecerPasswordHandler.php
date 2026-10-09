<?php

declare(strict_types=1);

namespace Courier\Auth\Application\RestablecerPassword;

use Courier\Auth\Domain\Exception\TokenRecuperacionInvalidoException;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Auth\Infrastructure\Persistence\PasswordResetTokenRepository;
use Courier\Auth\Infrastructure\Security\PasswordHasher;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;

final class RestablecerPasswordHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly PasswordResetTokenRepository $tokens,
        private readonly PasswordHasher $hasher,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RestablecerPasswordCommand);

        if (strlen($command->nuevaPassword) < 8) {
            throw new ValidationException('La contrasena debe tener al menos 8 caracteres.');
        }

        $datos = $this->tokens->validar($command->token);

        if ($datos === null) {
            throw new TokenRecuperacionInvalidoException('El enlace de recuperacion es invalido o ha expirado.');
        }

        $usuario = $this->usuarios->findById($datos['usuarioId']);

        if ($usuario === null) {
            throw new TokenRecuperacionInvalidoException('El enlace de recuperacion es invalido o ha expirado.');
        }

        $usuario->cambiarPassword($this->hasher->hash($command->nuevaPassword));
        $this->usuarios->save($usuario);
        $this->tokens->marcarUsado($command->token);

        return null;
    }
}
