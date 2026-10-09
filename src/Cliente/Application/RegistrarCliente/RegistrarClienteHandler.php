<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\RegistrarCliente;

use Courier\Cliente\Domain\Cliente;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Cliente\Domain\Exception\ClienteDuplicadoException;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\EmailAddress;
use Courier\Shared\Domain\ValueObject\Uuid;

final class RegistrarClienteHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ClienteRepositoryInterface $clientes)
    {
    }

    public function handle(CommandInterface $command): RegistrarClienteResult
    {
        assert($command instanceof RegistrarClienteCommand);

        if ($command->pais === null || trim($command->pais) === '') {
            throw new ValidationException('Debe seleccionar el pais del cliente.');
        }

        if ($this->clientes->existsEmail($command->email)) {
            throw new ClienteDuplicadoException("Ya existe un cliente con el correo {$command->email}.");
        }

        $cliente = Cliente::registrar(
            Uuid::generate(),
            $command->nombre,
            $command->apellido,
            new EmailAddress($command->email),
            $command->telefono,
            $command->direccion,
            $command->empresa,
            $command->pais,
        );

        $this->clientes->save($cliente);

        return new RegistrarClienteResult($cliente->id());
    }
}
