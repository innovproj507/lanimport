<?php

declare(strict_types=1);

namespace Courier\Salida\Application\ActualizarEmbarqueSalida;

use Courier\Naviera\Domain\NavieraRepositoryInterface;
use Courier\Salida\Domain\Exception\OrdenSalidaNotFoundException;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Salida\Domain\ValueObject\DatosEmbarque;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;

/** Guarda o corrige los datos de embarque (contenedor, sello, naviera, barco, fechas, destino). */
final class ActualizarEmbarqueSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly NavieraRepositoryInterface $navieras,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarEmbarqueSalidaCommand);

        $orden = $this->ordenes->findById($command->ordenId);

        if ($orden === null) {
            throw new OrdenSalidaNotFoundException('Orden de salida no encontrada.');
        }

        $navieraNombre = null;

        if ($command->navieraId !== null && $command->navieraId !== '') {
            $naviera = $this->navieras->findById($command->navieraId);

            // Una naviera desactivada solo se acepta si la orden ya la tenia.
            if ($naviera === null || (!$naviera->activo() && $orden->embarque()->navieraId !== $naviera->id())) {
                throw new ValidationException('Seleccione una naviera valida.');
            }

            $navieraNombre = $naviera->nombre();
        }

        $orden->actualizarEmbarque(DatosEmbarque::desdeArreglo([
            'numeroContenedor' => $command->numeroContenedor,
            'numeroSello' => $command->numeroSello,
            'navieraId' => $command->navieraId,
            'navieraNombre' => $navieraNombre,
            'nombreBarco' => $command->nombreBarco,
            'fechaCargue' => $command->fechaCargue,
            'fechaSalidaEtd' => $command->fechaSalidaEtd,
            'destinoFinal' => $command->destinoFinal,
        ]));

        $this->ordenes->save($orden);

        return null;
    }
}
