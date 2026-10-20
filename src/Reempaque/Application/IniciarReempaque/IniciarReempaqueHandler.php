<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\IniciarReempaque;

use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Domain\ReempaqueOrden;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Reempaque\Domain\TipoReempaque;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class IniciarReempaqueHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly LpnRepositoryInterface $lpns,
        private readonly ReempaqueOrdenRepositoryInterface $ordenes,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof IniciarReempaqueCommand);

        if ($command->origenLpnIds === []) {
            throw new ValidationException('Seleccione al menos una carga con bultos listos para reempacar.');
        }

        $tipo = TipoReempaque::tryFrom($command->tipo);

        if ($tipo === null || !in_array($tipo, TipoReempaque::seleccionables(), true)) {
            throw new ValidationException('Seleccione el tipo de reempaque.');
        }
        $origenes = [];

        foreach ($command->origenLpnIds as $lpnId) {
            $lpn = $this->lpns->findById($lpnId);

            if ($lpn === null) {
                throw new ValidationException("No se encontro el LPN con id {$lpnId}.");
            }

            if ($lpn->estado() !== EstadoLpn::AVAILABLE) {
                throw new ValidationException("El LPN {$lpn->codigo()} ya no esta disponible (otra orden lo tomo o cambio de estado). Recargue la pagina.");
            }

            if ($lpn->clienteId() !== $command->clienteId) {
                throw new ValidationException("El LPN {$lpn->codigo()} no pertenece al cliente seleccionado.");
            }

            $origenes[] = $lpn;
        }

        return $this->unitOfWork->run(function () use ($command, $tipo, $origenes) {
            $orden = ReempaqueOrden::iniciar(
                Uuid::generate(),
                $tipo,
                $command->clienteId,
                array_map(fn ($lpn) => $lpn->id(), $origenes),
                $command->usuarioId,
                $command->instrucciones,
            );

            $this->ordenes->save($orden);

            foreach ($origenes as $lpn) {
                $lpn->enviarAReempaque();
                $this->lpns->save($lpn);
            }

            return $orden->id();
        });
    }
}
