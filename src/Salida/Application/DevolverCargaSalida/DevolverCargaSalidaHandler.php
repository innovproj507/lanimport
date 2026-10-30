<?php

declare(strict_types=1);

namespace Courier\Salida\Application\DevolverCargaSalida;

use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Saca una carga de una salida pendiente y la devuelve a bodega: sus bultos vuelven a estar
 * disponibles para otra salida. Si la salida se queda sin cargas, se elimina.
 */
final class DevolverCargaSalidaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly SalidaOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return int bultos devueltos */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DevolverCargaSalidaCommand);

        $orden = $this->ordenes->findById($command->ordenId) ?? throw new NotFoundException('Orden de salida no encontrada.');

        $porCodigo = [];
        foreach ($orden->lineas() as $linea) {
            $porCodigo[$linea->codigoLpn()] = $linea->lpnId();
        }

        $lpnIds = [];
        foreach ($this->ordenes->avancePorCarga($orden->id()) as $grupo) {
            if ($grupo['cargaId'] === $command->grupo) {
                foreach ($grupo['codigos'] as $codigo) {
                    if (isset($porCodigo[$codigo])) {
                        $lpnIds[] = $porCodigo[$codigo];
                    }
                }
            }
        }

        if ($lpnIds === []) {
            throw new ValidationException('Esa carga no esta en esta salida.');
        }

        $sacadas = $orden->sacarLineas($lpnIds);

        $this->unitOfWork->run(function () use ($orden, $sacadas): void {
            foreach ($sacadas as $linea) {
                $lpn = $this->lpns->findById($linea->lpnId());

                if ($lpn !== null) {
                    $lpn->liberarReserva();
                    $this->lpns->save($lpn);
                }
            }

            if ($orden->lineas() === []) {
                $this->ordenes->eliminar($orden->id());
            } else {
                $this->ordenes->save($orden);
            }
        });

        return count($sacadas);
    }
}
