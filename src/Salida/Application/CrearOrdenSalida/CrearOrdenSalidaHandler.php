<?php

declare(strict_types=1);

namespace Courier\Salida\Application\CrearOrdenSalida;

use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Naviera\Domain\NavieraRepositoryInterface;
use Courier\Salida\Domain\SalidaLinea;
use Courier\Salida\Domain\SalidaOrden;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Salida\Domain\ValueObject\DatosEmbarque;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Crea la salida (DO) con sus LPNs y, si vienen, los datos del contenedor. Los LPNs quedan
 * reservados hasta que bodega los escanee y se finalice la salida. Puede llevar cargas de
 * varios clientes; si todas son de uno, la salida queda a nombre de ese cliente.
 */
final class CrearOrdenSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly LpnRepositoryInterface $lpns,
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly UnitOfWork $unitOfWork,
        private readonly NavieraRepositoryInterface $navieras,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CrearOrdenSalidaCommand);

        if ($command->lpnIds === []) {
            throw new ValidationException('Seleccione al menos una carga con bultos listos para la salida.');
        }

        // El contenedor se valida antes de crear nada: si esta mal escrito no queda un DO a medias.
        $embarque = $command->embarque !== null ? $this->datosEmbarque($command->embarque) : null;

        $lpnsSeleccionados = [];
        $clientes = [];

        foreach (array_unique($command->lpnIds) as $lpnId) {
            $lpn = $this->lpns->findById($lpnId);

            if ($lpn === null) {
                throw new ValidationException("No se encontro el LPN con id {$lpnId}.");
            }

            if ($lpn->estado() !== EstadoLpn::AVAILABLE) {
                throw new ValidationException("El LPN {$lpn->codigo()} ya no esta disponible (otra salida lo tomo o cambio de estado). Recargue la pagina.");
            }

            if ($command->clienteId !== null && $lpn->clienteId() !== $command->clienteId) {
                throw new ValidationException("El LPN {$lpn->codigo()} no pertenece al cliente seleccionado.");
            }

            $lpnsSeleccionados[] = $lpn;
            $clientes[$lpn->clienteId()] = true;
        }

        $clienteId = $command->clienteId ?? (count($clientes) === 1 ? (string) array_key_first($clientes) : null);

        return $this->unitOfWork->run(function () use ($command, $lpnsSeleccionados, $clienteId, $embarque) {
            $lineas = array_map(
                fn ($lpn) => new SalidaLinea($lpn->id(), (string) $lpn->codigo()),
                $lpnsSeleccionados,
            );

            $orden = SalidaOrden::crear(
                Uuid::generate(),
                $clienteId,
                $command->aduaneroIdentificador,
                $lineas,
                $command->usuarioId,
            );

            if ($embarque !== null) {
                $orden->actualizarEmbarque($embarque);
            }

            $this->ordenes->save($orden);

            foreach ($lpnsSeleccionados as $lpn) {
                $lpn->reservarParaSalida();
                $this->lpns->save($lpn);
            }

            return $orden->id();
        });
    }

    /** @param array<string, string|null> $datos */
    private function datosEmbarque(array $datos): DatosEmbarque
    {
        $navieraNombre = null;
        $navieraId = trim((string) ($datos['navieraId'] ?? ''));

        if ($navieraId !== '') {
            $naviera = $this->navieras->findById($navieraId);

            if ($naviera === null || !$naviera->activo()) {
                throw new ValidationException('Seleccione una naviera valida.');
            }

            $navieraNombre = $naviera->nombre();
        }

        return DatosEmbarque::desdeArreglo([
            'numeroContenedor' => $datos['numeroContenedor'] ?? null,
            'numeroSello' => $datos['numeroSello'] ?? null,
            'navieraId' => $navieraId !== '' ? $navieraId : null,
            'navieraNombre' => $navieraNombre,
            'nombreBarco' => $datos['nombreBarco'] ?? null,
            'fechaCargue' => $datos['fechaCargue'] ?? null,
            'fechaSalidaEtd' => $datos['fechaSalidaEtd'] ?? null,
            'destinoFinal' => $datos['destinoFinal'] ?? null,
        ]);
    }
}
