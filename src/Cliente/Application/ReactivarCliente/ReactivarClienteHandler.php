<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\ReactivarCliente;

use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

/** Vuelve a activar un cliente desactivado (aparece otra vez en Ingreso de Carga y Salidas). */
final class ReactivarClienteHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ClienteRepositoryInterface $clientes)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ReactivarClienteCommand);

        $cliente = $this->clientes->findById($command->clienteId);

        if ($cliente === null) {
            throw new NotFoundException('Cliente no encontrado.');
        }

        $cliente->reactivar();
        $this->clientes->save($cliente);

        return null;
    }
}
