<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\TerminarReempaque;

use Courier\Lpn\Domain\LpnBarcodeGeneratorInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\Exception\ReempaqueOrdenNotFoundException;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use Courier\Carga\Domain\BitacoraCargaInterface;

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
        private readonly ?BitacoraCargaInterface $bitacora = null,
        private readonly ?LpnBarcodeGeneratorInterface $etiquetas = null,
    ) {
    }

    /**
     * Etiqueta de cada caja: marca y su numero entre las cajas del reempaque ("1/4"), en el orden
     * de su codigo (LPN-R00002-1, -2...). Mismo archivo, asi se reimprime desde la orden.
     */
    public function dibujarEtiquetas(\Courier\Reempaque\Domain\ReempaqueOrden $orden): void
    {
        if ($this->etiquetas === null) {
            return;
        }

        $cajas = array_values(array_filter(array_map(fn ($id) => $this->lpns->findById($id), $orden->destinoLpnIds())));
        usort($cajas, static fn ($a, $b) => strnatcmp((string) $a->codigo(), (string) $b->codigo()));
        $total = count($cajas);

        foreach ($cajas as $i => $caja) {
            try {
                $this->etiquetas->generate((string) $caja->codigo(), $caja->marca(), null, null, $i + 1, $total, true, 0.35);
            } catch (\Throwable $e) {
                error_log('Etiqueta de caja ' . $caja->codigo() . ': ' . $e->getMessage());
            }
        }
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

        $this->dibujarEtiquetas($orden);

        $this->bitacora?->registrarPorLpns($orden->origenLpnIds(), 'reempaque', "{n} bulto(s) reempacados en caja(s): reempaque {$orden->codigo()} terminado");

        return null;
    }
}
