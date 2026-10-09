<?php

declare(strict_types=1);

namespace Courier\Cliente\Application\SolicitarRegistroCliente;

use Courier\Cliente\Domain\Cliente;
use Courier\Cliente\Domain\ClienteDocumento;
use Courier\Cliente\Domain\ClienteDocumentoRepositoryInterface;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Cliente\Domain\Exception\ClienteDuplicadoException;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\ValueObject\EmailAddress;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class SolicitarRegistroClienteHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ClienteRepositoryInterface $clientes,
        private readonly ClienteDocumentoRepositoryInterface $documentos,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): SolicitarRegistroClienteResult
    {
        assert($command instanceof SolicitarRegistroClienteCommand);

        if ($this->clientes->existsEmail($command->email)) {
            throw new ClienteDuplicadoException("Ya existe un cliente con el correo {$command->email}.");
        }

        $cliente = Cliente::solicitar(
            new Uuid($command->clienteId),
            $command->nombre,
            $command->apellido,
            new EmailAddress($command->email),
            $command->telefono,
            $command->direccion,
            $command->empresa,
            $command->pais,
        );

        $this->unitOfWork->run(function () use ($cliente, $command) {
            $this->clientes->save($cliente);

            foreach ($command->archivos as $archivo) {
                $this->documentos->save(ClienteDocumento::crear(
                    Uuid::generate(),
                    $cliente->id(),
                    $archivo['nombre_archivo'],
                    $archivo['ruta_archivo'],
                    $archivo['tipo'],
                ));
            }
        });

        $codigo = $this->clientes->findById($cliente->id())?->codigo();

        return new SolicitarRegistroClienteResult($cliente->id(), $codigo);
    }
}
