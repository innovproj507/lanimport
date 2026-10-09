<?php

declare(strict_types=1);

namespace Courier\Reporte\Infrastructure\Persistence;

use Courier\Carga\Domain\CargaStatus;
use Courier\Lpn\Domain\EstadoLpn;
use PDO;

final class PdoResumenDashboardRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function cargasHoy(): int
    {
        return (int) $this->connection->query(
            'SELECT COUNT(*) FROM cargas WHERE DATE(created_at) = CURDATE()'
        )->fetchColumn();
    }

    public function cargasEsteMes(): int
    {
        return (int) $this->connection->query(
            'SELECT COUNT(*) FROM cargas WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())'
        )->fetchColumn();
    }

    public function cargasMesAnterior(): int
    {
        return (int) $this->connection->query(
            'SELECT COUNT(*) FROM cargas
             WHERE YEAR(created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)
               AND MONTH(created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH)'
        )->fetchColumn();
    }

    public function pendientes(): int
    {
        $pendientesEstados = [
            CargaStatus::EN_BODEGA->value,
            CargaStatus::EN_TRANSITO->value,
            CargaStatus::EN_ADUANA->value,
        ];

        $placeholders = implode(',', array_fill(0, count($pendientesEstados), '?'));
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM cargas WHERE estado IN ({$placeholders})");
        $stmt->execute($pendientesEstados);

        return (int) $stmt->fetchColumn();
    }

    public function clientesActivos(): int
    {
        return (int) $this->connection->query(
            'SELECT COUNT(DISTINCT cliente_id) FROM cargas'
        )->fetchColumn();
    }

    public function totalHistorico(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM cargas')->fetchColumn();
    }

    /** @return array<int, array{nombre: string, totalCargas: int}> */
    public function topClientes(int $limite = 5): array
    {
        $stmt = $this->connection->prepare(
            'SELECT c.nombre AS nombre, COUNT(*) AS total
             FROM cargas ca
             JOIN clientes c ON c.id = ca.cliente_id
             GROUP BY c.id, c.nombre
             ORDER BY total DESC
             LIMIT ' . max(1, $limite)
        );
        $stmt->execute();

        return array_map(
            static fn (array $row) => ['nombre' => (string) $row['nombre'], 'totalCargas' => (int) $row['total']],
            $stmt->fetchAll(),
        );
    }

    /** @return array<string, int> */
    public function porServicioEsteMes(): array
    {
        $stmt = $this->connection->query(
            "SELECT COALESCE(tipo_servicio, 'sin_especificar') AS tipo_servicio, COUNT(*) AS total FROM cargas
             WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())
             GROUP BY tipo_servicio"
        );

        $resultado = ['aereo' => 0, 'maritimo' => 0, 'freight' => 0, 'sin_especificar' => 0];

        foreach ($stmt->fetchAll() as $row) {
            $resultado[(string) $row['tipo_servicio']] = (int) $row['total'];
        }

        return $resultado;
    }

    /** @return array<int, int> dia del mes => cargas ingresadas ese dia (mes actual completo, con ceros) */
    public function cargasPorDiaEsteMes(): array
    {
        return $this->porDiaEsteMes('cargas');
    }

    /** @return array<int, int> dia del mes => bultos (LPN) generados ese dia */
    public function bultosPorDiaEsteMes(): array
    {
        return $this->porDiaEsteMes('lpns');
    }

    /** @return array<int, int> */
    private function porDiaEsteMes(string $tabla): array
    {
        $stmt = $this->connection->query(
            "SELECT DAY(created_at) AS dia, COUNT(*) AS total FROM {$tabla}
             WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())
             GROUP BY DAY(created_at)"
        );

        $resultado = array_fill(1, (int) date('t'), 0);

        foreach ($stmt->fetchAll() as $row) {
            $resultado[(int) $row['dia']] = (int) $row['total'];
        }

        return $resultado;
    }

    /** @return array<string, int> 'YYYY-MM' => cargas, ultimos 12 meses incluido el actual */
    public function cargasPorMes(): array
    {
        $stmt = $this->connection->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS mes, COUNT(*) AS total FROM cargas
             WHERE created_at >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01')
             GROUP BY mes"
        );

        $resultado = [];
        $inicio = new \DateTimeImmutable('first day of this month');

        for ($i = 11; $i >= 0; $i--) {
            $resultado[$inicio->modify("-{$i} month")->format('Y-m')] = 0;
        }

        foreach ($stmt->fetchAll() as $row) {
            if (isset($resultado[(string) $row['mes']])) {
                $resultado[(string) $row['mes']] = (int) $row['total'];
            }
        }

        return $resultado;
    }

    /** @return array<string, array{label: string, total: int}> estado => cargas, solo estados con cargas */
    public function cargasPorEstado(): array
    {
        $stmt = $this->connection->query('SELECT estado, COUNT(*) AS total FROM cargas GROUP BY estado ORDER BY total DESC');

        $resultado = [];

        foreach ($stmt->fetchAll() as $row) {
            $resultado[(string) $row['estado']] = [
                'label' => CargaStatus::from((string) $row['estado'])->label(),
                'total' => (int) $row['total'],
            ];
        }

        return $resultado;
    }

    /** @return array<string, array{label: string, total: int}> todos los estados de LPN, con ceros */
    public function bultosPorEstado(): array
    {
        $resultado = [];

        foreach (EstadoLpn::cases() as $estado) {
            $resultado[$estado->value] = ['label' => $estado->label(), 'total' => 0];
        }

        foreach ($this->connection->query('SELECT estado, COUNT(*) AS total FROM lpns GROUP BY estado')->fetchAll() as $row) {
            $resultado[(string) $row['estado']]['total'] = (int) $row['total'];
        }

        return $resultado;
    }

    /** @return array{confirmadasEsteMes: int, pendientes: int} */
    public function salidas(): array
    {
        $row = $this->connection->query(
            "SELECT
                SUM(estado = 'confirmada' AND YEAR(confirmed_at) = YEAR(CURDATE()) AND MONTH(confirmed_at) = MONTH(CURDATE())) AS confirmadas,
                SUM(estado = 'pendiente') AS pendientes
             FROM salida_ordenes"
        )->fetch();

        return [
            'confirmadasEsteMes' => (int) ($row['confirmadas'] ?? 0),
            'pendientes' => (int) ($row['pendientes'] ?? 0),
        ];
    }

    public function clientesPorAprobar(): int
    {
        return (int) $this->connection->query("SELECT COUNT(*) FROM clientes WHERE estado = 'pendiente'")->fetchColumn();
    }

    /** @return array<int, array{nombre: string, total: int}> bultos en bodega por ubicacion (los sin ubicar aparte) */
    public function bultosPorUbicacion(int $limite = 5): array
    {
        $stmt = $this->connection->query(
            "SELECT COALESCE(u.codigo, 'Sin ubicacion') AS nombre, COUNT(*) AS total
             FROM lpns l
             LEFT JOIN ubicaciones u ON u.id = l.ubicacion_id
             WHERE l.estado <> 'consumed'
             GROUP BY nombre
             ORDER BY total DESC
             LIMIT " . max(1, $limite)
        );

        return array_map(
            static fn (array $row) => ['nombre' => (string) $row['nombre'], 'total' => (int) $row['total']],
            $stmt->fetchAll(),
        );
    }

    /** @return array{total: int, items: array<int, array{id: string, trackingNumero: string, clienteNombre: string, fecha: string}>} */
    public function cargasSinActaRecepcion(int $limite = 5): array
    {
        $desde = 'FROM cargas ca
                  JOIN clientes c ON c.id = ca.cliente_id
                  LEFT JOIN cargas_actas_recepcion a ON a.carga_id = ca.id
                  WHERE a.id IS NULL AND ca.estado NOT IN (\'entregado\')';

        $total = (int) $this->connection->query("SELECT COUNT(*) {$desde}")->fetchColumn();
        $stmt = $this->connection->query(
            "SELECT ca.id, ca.tracking_numero, c.nombre AS cliente_nombre, ca.fecha_ingreso {$desde}
             ORDER BY ca.fecha_ingreso ASC LIMIT " . max(1, $limite)
        );

        return [
            'total' => $total,
            'items' => array_map(
                static fn (array $row) => [
                    'id' => (string) $row['id'],
                    'trackingNumero' => (string) $row['tracking_numero'],
                    'clienteNombre' => (string) $row['cliente_nombre'],
                    'fecha' => (string) $row['fecha_ingreso'],
                ],
                $stmt->fetchAll(),
            ),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function cargasRecientes(int $limite = 8): array
    {
        $stmt = $this->connection->prepare(
            "SELECT ca.id, ca.tracking_numero, c.nombre AS cliente_nombre,
                    COALESCE(ca.tipo_servicio, 'sin_especificar') AS tipo_servicio, ca.estado, ca.created_at
             FROM cargas ca
             JOIN clientes c ON c.id = ca.cliente_id
             ORDER BY ca.created_at DESC
             LIMIT " . max(1, $limite)
        );
        $stmt->execute();

        return array_map(
            static fn (array $row) => [
                'id' => (string) $row['id'],
                'trackingNumero' => (string) $row['tracking_numero'],
                'clienteNombre' => (string) $row['cliente_nombre'],
                'tipoServicio' => (string) $row['tipo_servicio'],
                'estado' => (string) $row['estado'],
                'estadoLabel' => CargaStatus::from((string) $row['estado'])->label(),
                'fecha' => (string) $row['created_at'],
            ],
            $stmt->fetchAll(),
        );
    }
}
