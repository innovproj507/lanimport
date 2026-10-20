<?php

declare(strict_types=1);

namespace Courier\Salida\Application\ConfirmarSalida;

use Courier\Carga\Application\SincronizarEstadoCarga\SincronizarEstadoCargaCommand;
use Courier\Carga\Application\SincronizarEstadoCarga\SincronizarEstadoCargaHandler;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Salida\Domain\Exception\OrdenSalidaNotFoundException;
use Courier\Salida\Domain\SalidaOrden;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class ConfirmarSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
        private readonly SincronizarEstadoCargaHandler $sincronizarCarga,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ConfirmarSalidaCommand);

        $orden = $this->ordenes->findById($command->salidaOrdenId);

        if ($orden === null) {
            throw new OrdenSalidaNotFoundException("No se encontro la orden de salida con id {$command->salidaOrdenId}.");
        }

        $orden->confirmar($command->usuarioId);

        $cargaIds = [];

        $this->unitOfWork->run(function () use ($orden, &$cargaIds) {
            foreach ($orden->lineas() as $linea) {
                $lpn = $this->lpns->findById($linea->lpnId());

                if ($lpn !== null) {
                    $lpn->confirmarSalida();
                    $this->lpns->save($lpn);

                    // Una caja reempacada no tiene carga propia: cuentan las de los bultos que lleva.
                    foreach ($lpn->cargaId() !== null ? [$lpn->cargaId()] : $this->lpns->cargasContenidas($lpn->id()) as $cargaId) {
                        $cargaIds[$cargaId] = true;
                    }
                }
            }

            $this->ordenes->save($orden);
        });

        // Las cargas que salieron completas pasan a "En transito" y se avisa a cada cliente.
        [$detalle, $datosEmbarque] = $this->datosDespacho($orden);

        foreach (array_keys($cargaIds) as $cargaId) {
            $this->sincronizarCarga->handle(new SincronizarEstadoCargaCommand($cargaId, $command->usuarioId, $detalle, $datosEmbarque));
        }

        return null;
    }

    /** @return array{0: string, 1: array<string, string|null>} texto del historial y datos para el correo al cliente */
    private function datosDespacho(SalidaOrden $orden): array
    {
        $contenedor = $orden->embarque()->contenedorFormateado();

        return [$orden->etiquetaDo() . ($contenedor !== null ? ", contenedor {$contenedor}" : ''), $orden->datosParaCliente()];
    }
}
