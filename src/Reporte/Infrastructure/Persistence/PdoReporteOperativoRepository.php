<?php

declare(strict_types=1);

namespace Courier\Reporte\Infrastructure\Persistence;

use PDO;

final class PdoReporteOperativoRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<string, int> */
    public function cargasPorServicio(string $desde, string $hasta): array
    {
        $stmt = $this->connection->prepare(
            "SELECT COALESCE(tipo_servicio, 'sin_especificar') AS tipo_servicio, COUNT(*) AS total FROM cargas
             WHERE DATE(created_at) BETWEEN :desde AND :hasta
             GROUP BY tipo_servicio"
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);

        $resultado = ['aereo' => 0, 'maritimo' => 0, 'freight' => 0, 'sin_especificar' => 0];

        foreach ($stmt->fetchAll() as $row) {
            $resultado[(string) $row['tipo_servicio']] = (int) $row['total'];
        }

        return $resultado;
    }

    /** @return array<string, array{cantidad: int, total: float}> */
    public function facturacionPorEstado(string $desde, string $hasta): array
    {
        $stmt = $this->connection->prepare(
            'SELECT estado, COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total FROM facturas
             WHERE DATE(created_at) BETWEEN :desde AND :hasta
             GROUP BY estado'
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);

        $resultado = [
            'pendiente' => ['cantidad' => 0, 'total' => 0.0],
            'pagada' => ['cantidad' => 0, 'total' => 0.0],
            'anulada' => ['cantidad' => 0, 'total' => 0.0],
        ];

        foreach ($stmt->fetchAll() as $row) {
            $resultado[(string) $row['estado']] = [
                'cantidad' => (int) $row['cantidad'],
                'total' => (float) $row['total'],
            ];
        }

        return $resultado;
    }

    /** @return array<int, array{nombre: string, totalFacturado: float}> */
    public function topClientesFacturacion(string $desde, string $hasta, int $limite = 5): array
    {
        $stmt = $this->connection->prepare(
            'SELECT c.nombre AS nombre, COALESCE(SUM(f.total), 0) AS total
             FROM facturas f
             JOIN clientes c ON c.id = f.cliente_id
             WHERE DATE(f.created_at) BETWEEN :desde AND :hasta
               AND f.estado != \'anulada\'
             GROUP BY c.id, c.nombre
             ORDER BY total DESC
             LIMIT ' . max(1, $limite)
        );
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);

        return array_map(
            static fn (array $row) => ['nombre' => (string) $row['nombre'], 'totalFacturado' => (float) $row['total']],
            $stmt->fetchAll(),
        );
    }

    /** @return array<string, int> */
    public function inventarioPorEstado(): array
    {
        $stmt = $this->connection->query('SELECT estado, COUNT(*) AS total FROM lpns GROUP BY estado');

        $resultado = [
            'receiving' => 0,
            'available' => 0,
            'in_repack' => 0,
            'reserved' => 0,
            'quarantine' => 0,
            'consumed' => 0,
        ];

        foreach ($stmt->fetchAll() as $row) {
            $resultado[(string) $row['estado']] = (int) $row['total'];
        }

        return $resultado;
    }
}
