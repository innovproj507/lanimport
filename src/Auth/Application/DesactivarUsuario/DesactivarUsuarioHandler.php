<?php

declare(strict_types=1);

namespace Courier\Auth\Application\DesactivarUsuario;

use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

final class DesactivarUsuarioHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UsuarioRepositoryInterface $usuarios)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DesactivarUsuarioCommand);

        $usuario = $this->usuarios->findById($command->usuarioId);

        if ($usuario === null) {
            throw new NotFoundException("No se encontro el usuario con id {$command->usuarioId}.");
        }

        $usuario->desactivar();
        $this->usuarios->save($usuario);

        return null;
    }
}
