<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\EmpacarBultoReempaque;

use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnHandler;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\Exception\ReempaqueOrdenNotFoundException;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/** Bodega escanea un bulto de la orden y lo mete en la caja abierta (o lo saca si se equivoco). */
final class EmpacarBultoReempaqueHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ReempaqueOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return array{codigo: string, empacados: int, total: int, enCaja: int} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof EmpacarBultoReempaqueCommand);

        $orden = $this->ordenes->findById($command->reempaqueOrdenId);

        if ($orden === null) {
            throw new ReempaqueOrdenNotFoundException('Orden de reempaque no encontrada.');
        }

        // Mismo ajuste que la recepcion: el lector puede escribir el guion como apostrofo.
        $codigo = ConfirmarRecepcionLpnHandler::normalizarCodigo($command->codigoEscaneado);
        $lpn = $this->lpns->findByCodigo($codigo);

        if ($lpn === null) {
            throw new NotFoundException("No se encontro ningun LPN con el codigo {$codigo}.");
        }

        if ($command->quitar) {
            $orden->quitar($lpn->id());
        } else {
            $orden->empacar($lpn->id());
        }

        $this->unitOfWork->run(fn () => $this->ordenes->save($orden));

        $abierta = $orden->cajaAbierta();
        // Bultos distintos ya metidos en alguna caja (abierta o cerrada).
        $empacados = array_unique(array_merge(...array_values(array_column($orden->cajas(), 'contenido')) ?: [[]]));

        return [
            'codigo' => $codigo,
            'empacados' => count($empacados),
            'total' => count($orden->origenLpnIds()),
            'enCaja' => $abierta !== null ? count($orden->cajas()[$abierta]['contenido']) : 0,
        ];
    }
}
