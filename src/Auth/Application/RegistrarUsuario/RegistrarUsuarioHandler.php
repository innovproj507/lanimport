<?php

declare(strict_types=1);

namespace Courier\Auth\Application\RegistrarUsuario;

use Courier\Auth\Domain\Exception\EmailDuplicadoException;
use Courier\Auth\Domain\Rol;
use Courier\Auth\Domain\Usuario;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Auth\Infrastructure\Security\PasswordHasher;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\ValueObject\EmailAddress;
use Courier\Shared\Domain\ValueObject\Uuid;

final class RegistrarUsuarioHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly PasswordHasher $passwordHasher,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RegistrarUsuarioCommand);

        if ($this->usuarios->existsEmail($command->email)) {
            throw new EmailDuplicadoException("Ya existe un usuario con el correo {$command->email}.");
        }

        $usuario = Usuario::registrar(
            Uuid::generate(),
            $command->nombre,
            new EmailAddress($command->email),
            $this->passwordHasher->hash($command->password),
            Rol::from($command->rol),
        );

        $this->usuarios->save($usuario);

        return $usuario->id();
    }
}
