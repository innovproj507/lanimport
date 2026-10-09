<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\ActualizarCliente;

use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Cliente\Domain\Exception\ClienteDuplicadoException;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\EmailAddress;

final class ActualizarClienteHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ClienteRepositoryInterface $clientes)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarClienteCommand);

        $cliente = $this->clientes->findById($command->clienteId);

        if ($cliente === null) {
            throw new NotFoundException("No se encontro el cliente con id {$command->clienteId}.");
        }

        if ($command->pais === null || trim($command->pais) === '') {
            throw new ValidationException('Debe seleccionar el pais del cliente.');
        }

        if ($this->clientes->existsEmailExcluding($command->email, $command->clienteId)) {
            throw new ClienteDuplicadoException("Ya existe otro cliente con el correo {$command->email}.");
        }

        $cliente->actualizar(
            $command->nombre,
            $command->apellido,
            new EmailAddress($command->email),
            $command->telefono,
            $command->direccion,
            $command->empresa,
            $command->pais,
        );

        $this->clientes->save($cliente);

        return null;
    }
}
