<?php

declare(strict_types=1);

namespace Courier\Reporte\Application\ObtenerResumenDashboard;

use Courier\Reporte\Infrastructure\Persistence\PdoResumenDashboardRepository;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ObtenerResumenDashboardHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PdoResumenDashboardRepository $repository)
    {
    }

    public function handle(QueryInterface $query): ResumenDashboardView
    {
        assert($query instanceof ObtenerResumenDashboardQuery);

        return new ResumenDashboardView(
            $this->repository->cargasHoy(),
            $this->repository->cargasEsteMes(),
            $this->repository->cargasMesAnterior(),
            $this->repository->pendientes(),
            $this->repository->clientesActivos(),
            $this->repository->totalHistorico(),
            $this->repository->topClientes(),
            $this->repository->porServicioEsteMes(),
            $this->repository->cargasRecientes(),
            $this->repository->cargasPorDiaEsteMes(),
            $this->repository->bultosPorDiaEsteMes(),
            $this->repository->cargasPorMes(),
            $this->repository->cargasPorEstado(),
            $this->repository->bultosPorEstado(),
            $this->repository->salidas(),
            $this->repository->clientesPorAprobar(),
            $this->repository->bultosPorUbicacion(),
            $this->repository->cargasSinActaRecepcion(),
        );
    }
}
