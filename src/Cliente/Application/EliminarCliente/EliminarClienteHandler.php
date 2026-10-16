<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\EliminarCliente;

use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\RegistroEnUsoException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Elimina un cliente que nunca se uso (sin cargas, salidas, facturas ni reempaques).
 * Si tiene historial no se borra: se debe desactivar. Su usuario del portal, si
 * tenia, queda desactivado (no se borra porque puede tener tokens/registros).
 */
final class EliminarClienteHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ClienteRepositoryInterface $clientes,
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return string nombre del cliente eliminado */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EliminarClienteCommand);

        $cliente = $this->clientes->findById($command->clienteId);

        if ($cliente === null) {
            throw new NotFoundException('Cliente no encontrado.');
        }

        $referencias = $this->clientes->referencias($cliente->id());

        if ($referencias !== []) {
            throw RegistroEnUsoException::para("al cliente {$cliente->nombreCompleto()}", $referencias);
        }

        $this->unitOfWork->run(function () use ($cliente): void {
            $this->clientes->delete($cliente->id());

            $usuario = $cliente->usuarioId() !== null ? $this->usuarios->findById($cliente->usuarioId()) : null;

            if ($usuario !== null && $usuario->activo()) {
                $usuario->desactivar();
                $this->usuarios->save($usuario);
            }
        });

        return $cliente->nombreCompleto();
    }
}
