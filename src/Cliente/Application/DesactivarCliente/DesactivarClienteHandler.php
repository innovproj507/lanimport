<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\DesactivarCliente;

use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

final class DesactivarClienteHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ClienteRepositoryInterface $clientes)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DesactivarClienteCommand);

        $cliente = $this->clientes->findById($command->clienteId);

        if ($cliente === null) {
            throw new NotFoundException("No se encontro el cliente con id {$command->clienteId}.");
        }

        $cliente->desactivar();
        $this->clientes->save($cliente);

        return null;
    }
}
