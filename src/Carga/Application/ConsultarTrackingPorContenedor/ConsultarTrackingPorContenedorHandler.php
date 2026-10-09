<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ConsultarTrackingPorContenedor;

use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroHandler;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\ValueObject\Contenedor;
use Courier\Carga\Infrastructure\Persistence\PdoHistorialEstadoRepository;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ConsultarTrackingPorContenedorHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly CargaRepositoryInterface $cargas,
        private readonly PdoHistorialEstadoRepository $historialRepository,
        private readonly LpnRepositoryInterface $lpns,
    ) {
    }

    /** @return array<int, \Courier\Carga\Application\ConsultarTrackingPorNumero\TrackingView> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ConsultarTrackingPorContenedorQuery);

        $cargas = $this->cargas->findByContenedor(new Contenedor($query->numeroContenedor));

        return array_map(
            fn ($carga) => ConsultarTrackingPorNumeroHandler::toView($carga, $this->historialRepository, $this->lpns),
            $cargas,
        );
    }
}
