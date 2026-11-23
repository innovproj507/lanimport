<?php

declare(strict_types=1);

namespace Courier\Devolucion\Application\MoverOrdenDevolucion;

use Courier\Carga\Application\SincronizarEstadoCarga\SincronizarEstadoCargaCommand;
use Courier\Carga\Application\SincronizarEstadoCarga\SincronizarEstadoCargaHandler;
use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\CargaStatus;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Devolucion\Domain\OrdenDevolucion;
use Courier\Devolucion\Domain\OrdenDevolucionRepositoryInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Notificacion\Application\NotificarDevolucionProveedor\NotificarDevolucionProveedorHandler;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\BodegaActivaInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use Throwable;

/**
 * Lo que se hace sobre una orden de devolucion abierta: escanear un bulto al subirlo al camion,
 * corregir datos del camion/nota, quitar una carga, cancelar, o confirmar que el camion salio
 * (los bultos quedan devueltos al proveedor y se avisa a cada cliente).
 * La orden se lee bloqueada dentro de la transaccion: dos usuarios no se pisan.
 */
final class MoverOrdenDevolucionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly OrdenDevolucionRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
        private readonly SincronizarEstadoCargaHandler $sincronizar,
        private readonly CargaRepositoryInterface $cargas,
        private readonly ClienteRepositoryInterface $clientes,
        private readonly NotificarDevolucionProveedorHandler $notificar,
        private readonly ?BodegaActivaInterface $bodegaActiva = null,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    /** @return array{estado: string, progreso: array{escaneados: int, total: int}, mensaje: ?string} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof MoverOrdenDevolucionCommand);

        [$orden, $mensaje, $despues] = $this->unitOfWork->run(function () use ($command): array {
            $orden = $this->ordenes->findById($command->ordenId) ?? throw new NotFoundException('Orden de devolucion no encontrada.');

            $fija = $this->bodegaActiva?->fija();
            if ($fija !== null && $orden->bodegaId() !== null && $fija !== $orden->bodegaId()) {
                throw new ValidationException('Esta orden de devolucion es de otra bodega.');
            }

            return match ($command->accion) {
                MoverOrdenDevolucionCommand::ESCANEAR => $this->escanear($orden, (string) $command->codigo),
                MoverOrdenDevolucionCommand::DATOS => $this->datos($orden, $command),
                MoverOrdenDevolucionCommand::QUITAR => $this->quitar($orden, (string) $command->cargaId),
                MoverOrdenDevolucionCommand::CANCELAR => $this->cancelar($orden),
                MoverOrdenDevolucionCommand::CONFIRMAR => $this->confirmar($orden, $command->usuarioId),
                default => throw new ValidationException('Accion no valida.'),
            };
        });

        // Fuera de la transaccion: bitacora, estado de las cargas y correos.
        if ($despues !== null) {
            $despues();
        }

        return ['estado' => $orden->estado()->value, 'progreso' => $orden->progreso(), 'mensaje' => $mensaje];
    }

    /** @return array{0: OrdenDevolucion, 1: ?string, 2: ?callable} */
    private function escanear(OrdenDevolucion $orden, string $codigo): array
    {
        if (trim($codigo) === '') {
            throw new ValidationException('Escanee o escriba el codigo del bulto.');
        }

        $lpnId = $orden->escanear($codigo);
        if ($lpnId === null) {
            return [$orden, 'Ese bulto ya estaba escaneado.', null];
        }

        $this->ordenes->save($orden);
        $p = $orden->progreso();

        return [$orden, $p['escaneados'] === $p['total'] ? 'Todos los bultos escaneados: ya se puede confirmar la salida del camion.' : null, null];
    }

    /** @return array{0: OrdenDevolucion, 1: ?string, 2: ?callable} */
    private function datos(OrdenDevolucion $orden, MoverOrdenDevolucionCommand $command): array
    {
        $orden->actualizarDatos($command->transporte ?? $orden->transporte(), $command->nota, $command->motivo);
        $this->ordenes->save($orden);

        return [$orden, 'Datos de la devolucion guardados.', null];
    }

    /** @return array{0: OrdenDevolucion, 1: ?string, 2: ?callable} */
    private function quitar(OrdenDevolucion $orden, string $cargaId): array
    {
        $quitados = $orden->quitarCarga($cargaId);
        $this->liberar($quitados);
        $this->ordenes->save($orden);

        return [$orden, count($quitados) . ' bulto(s) quitados de la orden: vuelven a estar disponibles en bodega.',
            fn () => $this->bitacora?->registrarPorLpns($quitados, 'devolucion_orden', "{n} bulto(s) quitados de la orden de devolucion {$orden->etiqueta()}: siguen en bodega")];
    }

    /** @return array{0: OrdenDevolucion, 1: ?string, 2: ?callable} */
    private function cancelar(OrdenDevolucion $orden): array
    {
        $liberados = $orden->cancelar();
        $this->liberar($liberados);
        $this->ordenes->save($orden);

        return [$orden, "Orden {$orden->etiqueta()} cancelada: los bultos siguen disponibles en bodega.",
            fn () => $this->bitacora?->registrarPorLpns($liberados, 'devolucion_orden', "Orden de devolucion {$orden->etiqueta()} cancelada: {n} bulto(s) siguen en bodega")];
    }

    /** @return array{0: OrdenDevolucion, 1: ?string, 2: ?callable} */
    private function confirmar(OrdenDevolucion $orden, string $usuarioId): array
    {
        $orden->confirmar($usuarioId);

        foreach ($orden->lpnIds() as $lpnId) {
            $lpn = $this->lpns->findById($lpnId);
            if ($lpn !== null) {
                $lpn->confirmarDevolucion();
                $this->lpns->save($lpn);
            }
        }
        $this->ordenes->save($orden);

        $total = count($orden->lpnIds());
        $camion = $orden->transporte()->resumen();

        return [$orden, "Orden {$orden->etiqueta()} confirmada: {$total} bulto(s) devueltos al proveedor.", function () use ($orden, $usuarioId, $camion): void {
            $this->bitacora?->registrarPorLpns(
                $orden->lpnIds(),
                'devuelta_proveedor',
                "{n} bulto(s) devueltos al proveedor en {$orden->etiqueta()}" . ($camion !== '' ? " ({$camion})" : '') . ". Motivo: {$orden->motivo()}",
            );
            $this->despuesDeConfirmar($orden, $usuarioId);
        }];
    }

    /** Estado de cada carga segun lo que le queda, y aviso a su cliente. Una falla no deshace la devolucion. */
    private function despuesDeConfirmar(OrdenDevolucion $orden, string $usuarioId): void
    {
        foreach ($orden->lpnsPorCarga() as $cargaId => $lpnIds) {
            if ($cargaId === '') {
                continue;
            }

            try {
                $this->sincronizar->handle(new SincronizarEstadoCargaCommand($cargaId, $usuarioId));
                $carga = $this->cargas->findById($cargaId);
                $cliente = $carga !== null ? $this->clientes->findById($carga->clienteId()) : null;

                if ($carga !== null && $cliente !== null) {
                    $this->notificar->handle(
                        $cargaId,
                        (string) $cliente->email(),
                        $cliente->nombreCompleto(),
                        (string) $carga->trackingNumero(),
                        $carga->proveedor(),
                        count($lpnIds),
                        $carga->estado() === CargaStatus::DEVUELTO_PROVEEDOR,
                        $orden->motivo(),
                    );
                }
            } catch (Throwable $e) {
                error_log("Devolucion {$orden->etiqueta()}, carga {$cargaId}: {$e->getMessage()}");
            }
        }
    }

    /** @param array<int, string> $lpnIds */
    private function liberar(array $lpnIds): void
    {
        foreach ($lpnIds as $lpnId) {
            $lpn = $this->lpns->findById($lpnId);
            if ($lpn !== null) {
                $lpn->liberarDeDevolucion();
                $this->lpns->save($lpn);
            }
        }
    }
}
