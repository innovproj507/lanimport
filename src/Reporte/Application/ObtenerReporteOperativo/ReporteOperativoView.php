<?php

declare(strict_types=1);

namespace Courier\Reporte\Application\ObtenerReporteOperativo;

final class ReporteOperativoView
{
    /**
     * @param array<string, int> $cargasPorServicio
     * @param array<string, array{cantidad: int, total: float}> $facturacion
     * @param array<int, array{nombre: string, totalFacturado: float}> $topClientesFacturacion
     * @param array<string, int> $inventarioPorEstado
     */
    public function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly array $cargasPorServicio,
        public readonly array $facturacion,
        public readonly array $topClientesFacturacion,
        public readonly array $inventarioPorEstado,
    ) {
    }

    public function totalCobrado(): float
    {
        return $this->facturacion['pagada']['total'] ?? 0.0;
    }

    public function totalPendiente(): float
    {
        return $this->facturacion['pendiente']['total'] ?? 0.0;
    }

    public function totalAnulado(): float
    {
        return $this->facturacion['anulada']['total'] ?? 0.0;
    }
}
