<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\CancelarReempaque;

use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\Exception\ReempaqueOrdenNotFoundException;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use Courier\Carga\Domain\BitacoraCargaInterface;

final class CancelarReempaqueHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ReempaqueOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CancelarReempaqueCommand);

        $orden = $this->ordenes->findById($command->reempaqueOrdenId);

        if ($orden === null) {
            throw new ReempaqueOrdenNotFoundException("No se encontro la orden de reempaque con id {$command->reempaqueOrdenId}.");
        }

        // Las cajas nuevas (abiertas o cerradas) se descartan: los bultos vuelven a quedar libres.
        $cajas = $orden->destinoLpnIds();
        $orden->cancelar();

        $this->unitOfWork->run(function () use ($orden, $cajas) {
            foreach ($orden->origenLpnIds() as $origenId) {
                $lpn = $this->lpns->findById($origenId);

                if ($lpn !== null) {
                    $lpn->liberarDeReempaque();
                    $this->lpns->save($lpn);
                }
            }

            $this->ordenes->save($orden);

            foreach ($cajas as $cajaId) {
                $this->lpns->deleteById($cajaId);
            }
        });

        $this->bitacora?->registrarPorLpns($orden->origenLpnIds(), 'reempaque', "{n} bulto(s) liberados: reempaque {$orden->codigo()} cancelado");

        return null;
    }
}
