<?php

declare(strict_types=1);

namespace Courier\Reporte\Application\ObtenerReporteOperativo;

use Courier\Reporte\Infrastructure\Persistence\PdoReporteOperativoRepository;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ObtenerReporteOperativoHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PdoReporteOperativoRepository $repository)
    {
    }

    public function handle(QueryInterface $query): ReporteOperativoView
    {
        assert($query instanceof ObtenerReporteOperativoQuery);

        return new ReporteOperativoView(
            $query->desde,
            $query->hasta,
            $this->repository->cargasPorServicio($query->desde, $query->hasta),
            $this->repository->facturacionPorEstado($query->desde, $query->hasta),
            $this->repository->topClientesFacturacion($query->desde, $query->hasta),
            $this->repository->inventarioPorEstado(),
        );
    }
}
