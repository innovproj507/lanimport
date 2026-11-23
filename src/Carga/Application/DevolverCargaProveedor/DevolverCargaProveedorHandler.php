<?php

declare(strict_types=1);

namespace Courier\Carga\Application\DevolverCargaProveedor;

use Courier\Carga\Application\SincronizarEstadoCarga\SincronizarEstadoCargaCommand;
use Courier\Carga\Application\SincronizarEstadoCarga\SincronizarEstadoCargaHandler;
use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\DevolucionProveedorRepositoryInterface;
use Courier\Carga\Domain\ImagenesEtiquetasCargaInterface;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Notificacion\Application\NotificarDevolucionProveedor\NotificarDevolucionProveedorHandler;
use Courier\Notificacion\Domain\Notificacion;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Devuelve al proveedor toda la carga o parte de sus bultos (el cliente ya no la quiere).
 * Nada se borra: los bultos quedan "Devuelto al proveedor" (fuera del inventario), la carga pasa a
 * "Devuelta al proveedor" cuando ya no le queda ninguno, y la devolucion queda registrada con su
 * motivo (y luego sus documentos). Solo bultos en bodega: los que estan en una OS/DO, reempaque o
 * traslado hay que sacarlos primero.
 */
final class DevolverCargaProveedorHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly LpnRepositoryInterface $lpns,
        private readonly DevolucionProveedorRepositoryInterface $devoluciones,
        private readonly UnitOfWork $unitOfWork,
        private readonly SincronizarEstadoCargaHandler $sincronizar,
        private readonly PdoHistorialEstadoRepository $historial,
        private readonly ImagenesEtiquetasCargaInterface $imagenesEtiquetas,
        private readonly ClienteRepositoryInterface $clientes,
        private readonly NotificarDevolucionProveedorHandler $notificar,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    /** @return array{devolucionId: string, tracking: string, bultos: int, completa: bool, notificacion: ?Notificacion} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof DevolverCargaProveedorCommand);

        $carga = $this->cargas->findById($command->cargaId) ?? throw new NotFoundException('Carga no encontrada.');
        $motivo = trim($command->motivo);

        if (mb_strlen($motivo) < 3) {
            throw new ValidationException('Escriba el motivo de la devolucion al proveedor.');
        }

        if (!$carga->estado()->permiteDevolucionProveedor()) {
            throw new ValidationException("La carga esta {$carga->estado()->label()}: ya no se puede devolver al proveedor.");
        }

        $todos = $this->lpns->findByCargaId($carga->id());
        $devolucionId = (string) Uuid::generate();
        $tracking = (string) $carga->trackingNumero();

        if ($todos === []) {
            // Sin etiquetas: se devuelve la carga completa, con los bultos declarados.
            $bultos = (int) round(array_sum(array_map(static fn ($l) => $l->cantidad(), $carga->lineas())));
            $antes = count($carga->historial());
            $carga->devolverAProveedor($command->usuarioId, "Devuelta al proveedor. Motivo: {$motivo}");

            $this->unitOfWork->run(function () use ($carga, $antes, $devolucionId, $bultos, $motivo, $command): void {
                $this->cargas->save($carga);
                foreach (array_slice($carga->historial(), $antes) as $entrada) {
                    $this->historial->append($carga->id(), $entrada);
                }
                $this->devoluciones->registrar($devolucionId, $carga->id(), $bultos, true, $motivo, $command->usuarioId, []);
            });

            $this->bitacora?->registrar($carga->id(), 'devuelta_proveedor', "Carga devuelta al proveedor ({$bultos} bulto(s) declarados). Motivo: {$motivo}");

            return $this->resultado($carga->id(), $devolucionId, $tracking, $bultos, true, $motivo);
        }

        $porId = [];
        foreach ($todos as $lpn) {
            $porId[$lpn->id()] = $lpn;
        }

        $ids = $command->lpnIds ?? array_keys(array_filter($porId, static fn ($l) => $l->estado()->devolvible()));
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            throw new ValidationException('Esta carga no tiene bultos en bodega para devolver (estan en una orden de salida, reempaque o traslado, o ya salieron).');
        }

        $elegidos = [];
        foreach ($ids as $id) {
            $lpn = $porId[$id] ?? throw new ValidationException('Uno de los bultos elegidos no es de esta carga. Recargue la pagina.');

            if (!$lpn->estado()->devolvible()) {
                $razon = match ($lpn->estado()) {
                    EstadoLpn::RESERVED => 'esta en una orden de salida: sáquelo primero de la OS/DO',
                    EstadoLpn::IN_REPACK => 'esta en reempaque',
                    EstadoLpn::IN_TRANSFER => 'esta en un traslado',
                    EstadoLpn::RETURNED => 'ya fue devuelto',
                    default => 'ya no esta en bodega (' . mb_strtolower($lpn->estado()->label()) . ')',
                };
                throw new ValidationException("El bulto {$lpn->codigo()} {$razon}.");
            }

            $elegidos[] = $lpn;
        }

        // Completa si despues de esta devolucion no le queda a la carga ningun bulto sin devolver.
        $yaDevueltos = count(array_filter($porId, static fn ($l) => $l->estado() === EstadoLpn::RETURNED));
        $completa = $yaDevueltos + count($elegidos) >= count($porId);

        $this->unitOfWork->run(function () use ($elegidos, $devolucionId, $carga, $completa, $motivo, $command): void {
            foreach ($elegidos as $lpn) {
                $lpn->devolverAProveedor();
                $this->lpns->save($lpn);
            }
            $this->devoluciones->registrar($devolucionId, $carga->id(), count($elegidos), $completa, $motivo, $command->usuarioId, array_map(static fn ($l) => $l->id(), $elegidos));
        });

        $this->bitacora?->registrarPorLpns(
            array_map(static fn ($l) => $l->id(), $elegidos),
            'devuelta_proveedor',
            '{n} bulto(s) devueltos al proveedor' . ($completa ? '' : ' (parcial)') . ". Motivo: {$motivo}",
        );

        // Estado de la carga segun lo que le queda (todo devuelto, o lo que queda ya salio).
        $this->sincronizar->handle(new SincronizarEstadoCargaCommand($carga->id(), $command->usuarioId));

        return $this->resultado($carga->id(), $devolucionId, $tracking, count($elegidos), $completa, $motivo);
    }

    /** @return array{devolucionId: string, tracking: string, bultos: int, completa: bool, notificacion: ?Notificacion} */
    private function resultado(string $cargaId, string $devolucionId, string $tracking, int $bultos, bool $completa, string $motivo): array
    {
        if ($completa) {
            $this->imagenesEtiquetas->liberar($cargaId);
        }

        $carga = $this->cargas->findById($cargaId);
        $cliente = $carga !== null ? $this->clientes->findById($carga->clienteId()) : null;
        $notificacion = $cliente !== null
            ? $this->notificar->handle($cargaId, (string) $cliente->email(), $cliente->nombreCompleto(), $tracking, (string) $carga->proveedor(), $bultos, $completa, $motivo)
            : null;

        return ['devolucionId' => $devolucionId, 'tracking' => $tracking, 'bultos' => $bultos, 'completa' => $completa, 'notificacion' => $notificacion];
    }
}
