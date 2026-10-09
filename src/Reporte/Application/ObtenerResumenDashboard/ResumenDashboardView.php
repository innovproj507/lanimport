<?php

declare(strict_types=1);

namespace Courier\Reporte\Application\ObtenerResumenDashboard;

final class ResumenDashboardView
{
    /**
     * @param array<int, array{nombre: string, totalCargas: int}> $topClientes
     * @param array<string, int> $porServicioEsteMes
     * @param array<int, array{id: string, trackingNumero: string, clienteNombre: string, tipoServicio: string, estado: string, estadoLabel: string, fecha: string}> $cargasRecientes
     * @param array<int, int> $cargasPorDia dia del mes actual => cargas
     * @param array<int, int> $bultosPorDia dia del mes actual => bultos (LPN)
     * @param array<string, int> $cargasPorMes 'YYYY-MM' => cargas (ultimos 12 meses)
     * @param array<string, array{label: string, total: int}> $cargasPorEstado
     * @param array<string, array{label: string, total: int}> $bultosPorEstado
     * @param array{confirmadasEsteMes: int, pendientes: int} $salidas
     * @param array<int, array{nombre: string, total: int}> $bultosPorUbicacion
     * @param array{total: int, items: array<int, array{id: string, trackingNumero: string, clienteNombre: string, fecha: string}>} $cargasSinActa
     */
    public function __construct(
        public readonly int $cargasHoy,
        public readonly int $cargasEsteMes,
        public readonly int $cargasMesAnterior,
        public readonly int $pendientes,
        public readonly int $clientesActivos,
        public readonly int $totalHistorico,
        public readonly array $topClientes,
        public readonly array $porServicioEsteMes,
        public readonly array $cargasRecientes,
        public readonly array $cargasPorDia = [],
        public readonly array $bultosPorDia = [],
        public readonly array $cargasPorMes = [],
        public readonly array $cargasPorEstado = [],
        public readonly array $bultosPorEstado = [],
        public readonly array $salidas = ['confirmadasEsteMes' => 0, 'pendientes' => 0],
        public readonly int $clientesPorAprobar = 0,
        public readonly array $bultosPorUbicacion = [],
        public readonly array $cargasSinActa = ['total' => 0, 'items' => []],
    ) {
    }

    /** Bultos fisicamente en bodega (todo menos los ya consumidos/despachados). */
    public function bultosEnBodega(): int
    {
        $total = 0;

        foreach ($this->bultosPorEstado as $estado => $grupo) {
            if ($estado !== 'consumed') {
                $total += $grupo['total'];
            }
        }

        return $total;
    }

    public function variacionVsMesAnterior(): ?float
    {
        if ($this->cargasMesAnterior === 0) {
            return null;
        }

        return round((($this->cargasEsteMes - $this->cargasMesAnterior) / $this->cargasMesAnterior) * 100, 1);
    }
}
