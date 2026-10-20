<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\TerminarReempaque;

use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\Exception\ReempaqueOrdenNotFoundException;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Todo quedo empacado: los bultos originales salen del inventario (consumidos) y las cajas
 * nuevas quedan disponibles para salir en un contenedor.
 */
final class TerminarReempaqueHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ReempaqueOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof TerminarReempaqueCommand);

        $orden = $this->ordenes->findById($command->reempaqueOrdenId);

        if ($orden === null) {
            throw new ReempaqueOrdenNotFoundException('Orden de reempaque no encontrada.');
        }

        $orden->terminar();

        $this->unitOfWork->run(function () use ($orden) {
            foreach ($orden->origenLpnIds() as $origenId) {
                $lpn = $this->lpns->findById($origenId);
                $lpn?->consumirPorReempaque();
                if ($lpn !== null) {
                    $this->lpns->save($lpn);
                }
            }

            foreach ($orden->destinoLpnIds() as $destinoId) {
                $lpn = $this->lpns->findById($destinoId);
                $lpn?->liberarDeReempaque();
                if ($lpn !== null) {
                    $this->lpns->save($lpn);
                }
            }

            $this->ordenes->save($orden);
        });

        return null;
    }
}
