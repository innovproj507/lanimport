<?php

declare(strict_types=1);

namespace Courier\Factura\Application\CrearFactura;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Factura\Domain\Factura;
use Courier\Factura\Domain\FacturaRepositoryInterface;
use Courier\Factura\Domain\LineaFactura;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class CrearFacturaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ClienteRepositoryInterface $clientes,
        private readonly CargaRepositoryInterface $cargas,
        private readonly FacturaRepositoryInterface $facturas,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CrearFacturaCommand);

        $cliente = $this->clientes->findById($command->clienteId);

        if ($cliente === null) {
            throw new ValidationException('No se encontro el cliente seleccionado.');
        }

        if ($command->cargaId !== null) {
            $carga = $this->cargas->findById($command->cargaId);

            if ($carga === null || $carga->clienteId() !== $command->clienteId) {
                throw new ValidationException('La carga seleccionada no pertenece a este cliente.');
            }
        }

        $lineas = array_map(
            fn (array $linea) => new LineaFactura(
                (string) $linea['descripcion'],
                (float) $linea['cantidad'],
                (float) $linea['precioUnitario'],
            ),
            $command->lineas,
        );

        return $this->unitOfWork->run(function () use ($command, $lineas) {
            $factura = Factura::crear(
                Uuid::generate(),
                $command->clienteId,
                $command->cargaId,
                $command->moneda,
                $lineas,
                $command->notas,
                $command->usuarioId,
            );

            $this->facturas->save($factura);

            return $factura->id();
        });
    }
}
