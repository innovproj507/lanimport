<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\CompletarReempaque;

use Courier\Carga\Infrastructure\Persistence\PdoEtiquetaRepository;
use Courier\Lpn\Domain\Lpn;
use Courier\Lpn\Domain\LpnBarcodeGeneratorInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\Exception\ReempaqueOrdenNotFoundException;
use Courier\Reempaque\Domain\GenealogiaLpnRepositoryInterface;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class CompletarReempaqueHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ReempaqueOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly GenealogiaLpnRepositoryInterface $genealogia,
        private readonly LpnBarcodeGeneratorInterface $barcodeGenerator,
        private readonly PdoEtiquetaRepository $etiquetas,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CompletarReempaqueCommand);

        if ($command->cantidadDestinos < 1) {
            throw new ValidationException('Debe generar al menos un LPN destino.');
        }

        $orden = $this->ordenes->findById($command->reempaqueOrdenId);

        if ($orden === null) {
            throw new ReempaqueOrdenNotFoundException("No se encontro la orden de reempaque con id {$command->reempaqueOrdenId}.");
        }

        if ($orden->codigo() === null) {
            throw new ValidationException('La orden de reempaque no tiene codigo asignado.');
        }

        $sufijoOrden = substr($orden->codigo(), -6);
        $anchoConsecutivo = strlen((string) $command->cantidadDestinos);

        $this->unitOfWork->run(function () use ($orden, $command, $sufijoOrden, $anchoConsecutivo) {
            $destinoIds = [];

            for ($i = 0; $i < $command->cantidadDestinos; $i++) {
                $codigo = 'LPN-' . $sufijoOrden . '-' . str_pad((string) ($i + 1), $anchoConsecutivo, '0', STR_PAD_LEFT);

                $destino = Lpn::generarDesdeReempaque(Uuid::generate(), $orden->clienteId(), $command->ubicacionId, $codigo);
                $this->lpns->save($destino);

                $etiquetaUrl = $this->barcodeGenerator->generate((string) $destino->codigo());
                $this->etiquetas->saveParaLpn($destino->id(), $etiquetaUrl);

                $destinoIds[] = $destino->id();
            }

            foreach ($orden->origenLpnIds() as $origenId) {
                foreach ($destinoIds as $destinoId) {
                    $this->genealogia->registrar($orden->id(), $origenId, $destinoId);
                }

                $origenLpn = $this->lpns->findById($origenId);

                if ($origenLpn !== null) {
                    $origenLpn->consumirPorReempaque();
                    $this->lpns->save($origenLpn);
                }
            }

            $orden->completar($destinoIds);
            $this->ordenes->save($orden);
        });

        return null;
    }
}
