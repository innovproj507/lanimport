<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ActualizarDatosCarga;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\ValueObject\Aduanero;
use Courier\Carga\Domain\ValueObject\Contenedor;
use Courier\Carga\Domain\ValueObject\LineaCarga;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use DateTimeImmutable;
use Exception;

final class ActualizarDatosCargaHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CargaRepositoryInterface $cargas)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarDatosCargaCommand);

        $carga = $this->cargas->findById($command->cargaId);

        if ($carga === null) {
            throw new NotFoundException("No se encontro la carga con id {$command->cargaId}.");
        }

        $aduanero = $command->aduaneroIdentificador !== null && $command->aduaneroIdentificador !== ''
            ? new Aduanero($command->aduaneroIdentificador, (string) $command->aduaneroNombre)
            : null;

        try {
            $fechaIngreso = new DateTimeImmutable($command->fechaIngreso);
        } catch (Exception $e) {
            throw new ValidationException('Fecha de ingreso invalida.');
        }

        $lineas = array_map(
            fn (array $linea) => LineaCarga::fromArray($linea),
            $command->lineas,
        );

        $carga->actualizarDatos(
            $command->clienteId,
            $command->proveedor,
            $command->proveedorIdentificador,
            $aduanero,
            $command->numeroContenedor !== null ? new Contenedor($command->numeroContenedor) : null,
            $fechaIngreso,
            $lineas,
            $command->notas,
            $command->facturaProveedorNombre,
            $command->facturaProveedorRuta,
        );

        $this->cargas->save($carga);

        return null;
    }
}
