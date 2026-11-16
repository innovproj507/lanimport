<?php

declare(strict_types=1);

namespace Courier\Traslado\Application\CrearTraslado;

use Courier\Bodega\Domain\BodegaRepositoryInterface;
use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Persistence\UnitOfWork;
use Courier\Traslado\Domain\Traslado;
use Courier\Traslado\Domain\TrasladoRepositoryInterface;

/** Arma un traslado: los bultos quedan "en traslado" (no salen en una OS) hasta que lleguen. */
final class CrearTrasladoHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly TrasladoRepositoryInterface $traslados,
        private readonly LpnRepositoryInterface $lpns,
        private readonly BodegaRepositoryInterface $bodegas,
        private readonly UnitOfWork $unitOfWork,
        private readonly ?BitacoraCargaInterface $bitacora = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CrearTrasladoCommand);

        $origen = $this->bodegas->findById($command->origenBodegaId) ?? throw new ValidationException('Bodega de origen no valida.');
        $destino = $this->bodegas->findById($command->destinoBodegaId);

        if ($destino === null || !$destino->activo()) {
            throw new ValidationException('Seleccione la bodega destino.');
        }

        $lpns = [];
        foreach (array_unique($command->lpnIds) as $lpnId) {
            $lpn = $this->lpns->findById((string) $lpnId) ?? throw new ValidationException('Uno de los bultos ya no existe. Recargue la pagina.');

            if ($lpn->bodegaId() !== $origen->id()) {
                throw new ValidationException("El bulto {$lpn->codigo()} no esta en {$origen->nombre()}.");
            }

            if ($lpn->estado() !== EstadoLpn::AVAILABLE) {
                throw new ValidationException("El bulto {$lpn->codigo()} ya no esta disponible ({$lpn->estado()->label()}). Recargue la pagina.");
            }

            $lpns[$lpn->id()] = $lpn;
        }

        $traslado = Traslado::crear(
            (string) Uuid::generate(),
            $origen->id(),
            $destino->id(),
            array_map(static fn ($l) => (string) $l->codigo(), $lpns),
            $command->usuarioId,
            $command->nota,
        );

        $this->unitOfWork->run(function () use ($traslado, $lpns): void {
            $this->traslados->save($traslado);

            foreach ($lpns as $lpn) {
                $lpn->enviarATraslado();
                $this->lpns->save($lpn);
            }
        });

        $codigo = $this->traslados->findById($traslado->id())?->etiqueta() ?? '';
        $this->bitacora?->registrarPorLpns(array_keys($lpns), 'traslado', "{n} bulto(s) apartados para el traslado {$codigo} de {$origen->nombre()} a {$destino->nombre()}");

        return $traslado->id();
    }
}
