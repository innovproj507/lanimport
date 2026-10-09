<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\CancelarReempaque;

use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\Exception\ReempaqueOrdenNotFoundException;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class CancelarReempaqueHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ReempaqueOrdenRepositoryInterface $ordenes,
        private readonly LpnRepositoryInterface $lpns,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CancelarReempaqueCommand);

        $orden = $this->ordenes->findById($command->reempaqueOrdenId);

        if ($orden === null) {
            throw new ReempaqueOrdenNotFoundException("No se encontro la orden de reempaque con id {$command->reempaqueOrdenId}.");
        }

        $orden->cancelar();

        $this->unitOfWork->run(function () use ($orden) {
            foreach ($orden->origenLpnIds() as $origenId) {
                $lpn = $this->lpns->findById($origenId);

                if ($lpn !== null) {
                    $lpn->liberarDeReempaque();
                    $this->lpns->save($lpn);
                }
            }

            $this->ordenes->save($orden);
        });

        return null;
    }
}
