<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\RechazarCliente;

use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;

final class RechazarClienteHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ClienteRepositoryInterface $clientes)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RechazarClienteCommand);

        $cliente = $this->clientes->findById($command->clienteId);

        if ($cliente === null) {
            throw new NotFoundException("No se encontro el cliente con id {$command->clienteId}.");
        }

        $cliente->rechazar();
        $this->clientes->save($cliente);

        return null;
    }
}
