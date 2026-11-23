<?php

declare(strict_types=1);

namespace Courier\Devolucion\Application\CrearOrdenDevolucion;

use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Devolucion\Domain\OrdenDevolucion;
use Courier\Devolucion\Domain\OrdenDevolucionRepositoryInterface;
use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Arma una orden de devolucion al proveedor con bultos disponibles de la bodega (de una o varias
 * cargas, de cualquier cliente y proveedor). Los bultos quedan "En devolucion": no salen en una OS,
 * reempaque ni traslado hasta que la orden se confirme o se cancele.
 */
final class CrearOrdenDevolucionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly OrdenDevolucionRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    /** @return string id de la orden */
    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CrearOrdenDevolucionCommand);

        $id = (string) Uuid::generate();

        $lpns = $this->unitOfWork->run(function () use ($command, $id): array {
            $lpns = [];
            foreach (array_unique($command->lpnIds) as $lpnId) {
                $lpn = $this->lpns->findById((string) $lpnId) ?? throw new ValidationException('Uno de los bultos ya no existe. Recargue la pagina.');

                if ($lpn->bodegaId() !== $command->bodegaId) {
                    throw new ValidationException("El bulto {$lpn->codigo()} esta en otra bodega.");
                }

                if ($lpn->estado() !== EstadoLpn::AVAILABLE) {
                    throw new ValidationException("El bulto {$lpn->codigo()} ya no esta disponible ({$lpn->estado()->label()}). Recargue la pagina.");
                }

                $lpns[$lpn->id()] = $lpn;
            }

            $orden = OrdenDevolucion::crear(
                $id,
                $command->bodegaId,
                array_map(static fn ($l) => ['codigo' => (string) $l->codigo(), 'cargaId' => $l->cargaId()], $lpns),
                $command->motivo,
                $command->nota,
                $command->transporte,
                $command->usuarioId,
            );
            $this->ordenes->save($orden);

            foreach ($lpns as $lpn) {
                $lpn->enviarADevolucion();
                $this->lpns->save($lpn);
            }

            return $lpns;
        });

        $codigo = $this->ordenes->findById($id)?->etiqueta() ?? '';
        $this->bitacora?->registrarPorLpns(array_keys($lpns), 'devolucion_orden', "{n} bulto(s) en la orden de devolucion al proveedor {$codigo}");

        return $id;
    }
}
