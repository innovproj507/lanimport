<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\EliminarLpnsDeCarga;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnBarcodeGeneratorInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class EliminarLpnsDeCargaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly LpnRepositoryInterface $lpns,
        private readonly LpnBarcodeGeneratorInterface $barcodeGenerator,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EliminarLpnsDeCargaCommand);

        $carga = $this->cargas->findById($command->cargaId);

        if ($carga === null) {
            throw new CargaNotFoundException("No se encontro la carga con id {$command->cargaId}.");
        }

        $lpns = $this->lpns->findByCargaId($carga->id());

        if ($lpns === []) {
            throw new ValidationException('Esta carga no tiene LPNs generados.');
        }

        foreach ($lpns as $lpn) {
            if ($lpn->estado() !== EstadoLpn::RECEIVING) {
                throw new ValidationException(
                    'No se pueden eliminar los LPNs: al menos uno ya fue recibido o movido (' . $lpn->estado()->label() . ').'
                );
            }
        }

        $this->unitOfWork->run(function () use ($carga, $lpns) {
            $this->lpns->deleteByCargaId($carga->id());

            foreach ($lpns as $lpn) {
                if ($lpn->codigo() !== null) {
                    $this->barcodeGenerator->eliminar($lpn->codigo());
                }
            }
        });

        return null;
    }
}
