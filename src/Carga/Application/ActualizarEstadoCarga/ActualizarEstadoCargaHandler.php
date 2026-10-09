<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ActualizarEstadoCarga;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\CargaStatus;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;

final class ActualizarEstadoCargaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly PdoHistorialEstadoRepository $historialRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ActualizarEstadoCargaCommand);

        $carga = $this->cargas->findById($command->cargaId);

        if ($carga === null) {
            throw new CargaNotFoundException("No se encontro la carga con id {$command->cargaId}");
        }

        $nuevoEstado = CargaStatus::from($command->nuevoEstado);
        $carga->cambiarEstado($nuevoEstado, $command->usuarioId, $command->comentario);

        $this->unitOfWork->run(function () use ($carga) {
            $this->cargas->save($carga);

            $entries = $carga->historial();
            $ultimo = $entries[count($entries) - 1];
            $this->historialRepository->append($carga->id(), $ultimo);
        });

        return null;
    }
}
