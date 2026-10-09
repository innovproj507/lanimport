<?php

declare(strict_types=1);

namespace Courier\Auth\Application\ActualizarUsuario;

use Courier\Auth\Domain\Rol;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Auth\Infrastructure\Security\PasswordHasher;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

final class ActualizarUsuarioHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly PasswordHasher $passwordHasher,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarUsuarioCommand);

        $usuario = $this->usuarios->findById($command->usuarioId);

        if ($usuario === null) {
            throw new NotFoundException("No se encontro el usuario con id {$command->usuarioId}.");
        }

        $usuario->actualizar($command->nombre, Rol::from($command->rol));

        if ($command->nuevaPassword !== null && $command->nuevaPassword !== '') {
            $usuario->cambiarPassword($this->passwordHasher->hash($command->nuevaPassword));
        }

        $this->usuarios->save($usuario);

        return null;
    }
}
