<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\AbrirCajaReempaque;

use Courier\Lpn\Domain\Lpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\Exception\ReempaqueOrdenNotFoundException;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Bodega abre una caja/paleta nueva en la orden: se crea su LPN (LPN-R00012-1, -2...) con la
 * marca de los bultos que se reempacan. La etiqueta se genera al cerrarla, con su contenido.
 */
final class AbrirCajaReempaqueHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ReempaqueOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return array{lpnId: string, codigo: string} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof AbrirCajaReempaqueCommand);

        $orden = $this->ordenes->findById($command->reempaqueOrdenId);

        if ($orden === null) {
            throw new ReempaqueOrdenNotFoundException('Orden de reempaque no encontrada.');
        }

        // Marca de la caja: la de sus bultos (si hay varias, la mas repetida).
        $marcas = [];
        foreach ($orden->origenLpnIds() as $origenId) {
            $marca = $this->lpns->findById($origenId)?->marca();
            if ($marca !== null && $marca !== '') {
                $marcas[$marca] = ($marcas[$marca] ?? 0) + 1;
            }
        }
        arsort($marcas);

        $prefijo = 'LPN-R' . substr((string) $orden->codigo(), -5) . '-';
        $numero = count($orden->destinoLpnIds()) + 1;
        while ($this->lpns->findByCodigo($prefijo . $numero) !== null) {
            $numero++;
        }

        $caja = Lpn::abrirCajaReempaque(Uuid::generate(), $orden->clienteId(), $prefijo . $numero, array_key_first($marcas));

        $this->unitOfWork->run(function () use ($orden, $caja) {
            $this->lpns->save($caja);
            $orden->abrirCaja($caja->id());
            $this->ordenes->save($orden);
        });

        return ['lpnId' => $caja->id(), 'codigo' => (string) $caja->codigo()];
    }
}
