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

    /**
     * Indicadores de bodega para gerencia: movimiento del periodo y situacion actual.
     * "En bodega" = cargas Ingresado (por escanear) o En bodega; los bultos ya despachados
     * en salidas confirmadas se descuentan (salidas parciales).
     *
     * @return array<string, mixed>
     */
    public function operacionBodega(string $desde, string $hasta): array
    {
        $periodo = [':desde' => $desde, ':hasta' => $hasta];
        $uno = function (string $sql, array $params = []): array {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        };
        $todos = function (string $sql, array $params = []): array {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        // Bultos, peso y valor por carga, y bultos ya despachados (salidas confirmadas).
        $porCarga = "SELECT c.id, c.cliente_id, c.estado, c.fecha_ingreso, c.created_at,
                            COALESCE(l.bultos, 0) AS bultos, COALESCE(l.peso, 0) AS peso, COALESCE(l.valor, 0) AS valor,
                            COALESCE(d.despachados, 0) AS despachados
                     FROM cargas c
                     LEFT JOIN (SELECT carga_id, SUM(cantidad) AS bultos, SUM(peso) AS peso, SUM(valor) AS valor
                                FROM carga_lineas GROUP BY carga_id) l ON l.carga_id = c.id
                     LEFT JOIN (SELECT lp.carga_id, COUNT(*) AS despachados
                                FROM salida_lineas sl
                                JOIN salida_ordenes so ON so.id = sl.salida_orden_id AND so.estado = 'confirmada'
                                JOIN lpns lp ON lp.id = sl.lpn_id
                                GROUP BY lp.carga_id) d ON d.carga_id = c.id";
        $enBodega = "({$porCarga}) x";
        $soloEnBodega = "x.estado IN ('ingresado', 'en_bodega')";

        $ingresos = $uno(
            "SELECT COUNT(*) AS cargas, COALESCE(SUM(x.bultos), 0) AS bultos, COALESCE(SUM(x.peso), 0) AS peso, COALESCE(SUM(x.valor), 0) AS valor
             FROM ({$porCarga}) x WHERE DATE(x.fecha_ingreso) BETWEEN :desde AND :hasta",
            $periodo,
        );

        $recepciones = $uno(
            "SELECT COUNT(*) AS cargas,
                    AVG(TIMESTAMPDIFF(MINUTE, c.created_at, h.fecha)) / 60 AS horas_promedio
             FROM tracking_historial h
             JOIN cargas c ON c.id = h.carga_id
             WHERE h.estado = 'en_bodega' AND h.comentario LIKE 'Recepcion completa%'
               AND DATE(h.fecha) BETWEEN :desde AND :hasta",
            $periodo,
        );

        $despachos = $uno(
            "SELECT COUNT(DISTINCT so.id) AS salidas,
                    COUNT(DISTINCT NULLIF(so.numero_contenedor, '')) AS contenedores,
                    COUNT(sl.id) AS bultos
             FROM salida_ordenes so
             LEFT JOIN salida_lineas sl ON sl.salida_orden_id = so.id
             WHERE so.estado = 'confirmada' AND DATE(so.confirmed_at) BETWEEN :desde AND :hasta",
            $periodo,
        );

        $inventario = $uno(
            "SELECT COUNT(*) AS cargas, COUNT(DISTINCT x.cliente_id) AS clientes,
                    COALESCE(SUM(x.bultos - x.despachados), 0) AS bultos,
                    COALESCE(SUM(x.peso), 0) AS peso, COALESCE(SUM(x.valor), 0) AS valor
             FROM {$enBodega} WHERE {$soloEnBodega}"
        );

        $porEscanear = $uno(
            "SELECT COUNT(DISTINCT carga_id) AS cargas, COUNT(*) AS bultos FROM lpns WHERE estado = 'receiving'"
        );
        $sinEtiquetas = $uno(
            "SELECT COUNT(*) AS cargas, COALESCE(SUM(l.bultos), 0) AS bultos
             FROM cargas c
             LEFT JOIN (SELECT carga_id, SUM(cantidad) AS bultos FROM carga_lineas GROUP BY carga_id) l ON l.carga_id = c.id
             WHERE c.estado IN ('ingresado', 'en_bodega')
               AND NOT EXISTS (SELECT 1 FROM lpns WHERE lpns.carga_id = c.id)"
        );

        $antiguedad = [];
        foreach ($todos(
            "SELECT CASE WHEN DATEDIFF(CURDATE(), x.fecha_ingreso) <= 30 THEN '0-30'
                         WHEN DATEDIFF(CURDATE(), x.fecha_ingreso) <= 60 THEN '31-60'
                         WHEN DATEDIFF(CURDATE(), x.fecha_ingreso) <= 90 THEN '61-90'
                         ELSE '+90' END AS rango,
                    COUNT(*) AS cargas, COALESCE(SUM(x.bultos - x.despachados), 0) AS bultos
             FROM {$enBodega} WHERE {$soloEnBodega}
             GROUP BY rango"
        ) as $fila) {
            $antiguedad[$fila['rango']] = ['cargas' => (int) $fila['cargas'], 'bultos' => (int) $fila['bultos']];
        }

        $topClientes = array_map(static fn (array $f) => [
            'nombre' => (string) $f['nombre'],
            'cargas' => (int) $f['cargas'],
            'bultos' => (int) $f['bultos'],
            'diasMax' => (int) $f['dias_max'],
        ], $todos(
            "SELECT TRIM(CONCAT(cl.nombre, ' ', COALESCE(cl.apellido, ''))) AS nombre, COUNT(*) AS cargas,
                    COALESCE(SUM(x.bultos - x.despachados), 0) AS bultos,
                    MAX(DATEDIFF(CURDATE(), x.fecha_ingreso)) AS dias_max
             FROM {$enBodega}
             JOIN clientes cl ON cl.id = x.cliente_id
             WHERE {$soloEnBodega}
             GROUP BY cl.id, cl.nombre, cl.apellido
             ORDER BY bultos DESC LIMIT 10"
        ));

        // Escaneo pendiente desde hace mas de 24 h: bultos que bodega aun no confirma.
        $incompletas = array_map(static fn (array $f) => [
            'cargaId' => (string) $f['id'],
            'tracking' => (string) $f['tracking_numero'],
            'cliente' => (string) $f['cliente'],
            'total' => (int) $f['total'],
            'recibidos' => (int) $f['recibidos'],
            'horas' => (int) $f['horas'],
        ], $todos(
            "SELECT c.id, c.tracking_numero, TRIM(CONCAT(cl.nombre, ' ', COALESCE(cl.apellido, ''))) AS cliente,
                    COUNT(l.id) AS total, SUM(l.estado <> 'receiving') AS recibidos,
                    TIMESTAMPDIFF(HOUR, MIN(l.created_at), NOW()) AS horas
             FROM cargas c
             JOIN lpns l ON l.carga_id = c.id
             LEFT JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.estado IN ('ingresado', 'en_bodega')
             GROUP BY c.id, c.tracking_numero, cl.nombre, cl.apellido
             HAVING SUM(l.estado = 'receiving') > 0 AND horas >= 24
             ORDER BY horas DESC LIMIT 15"
        ));

        return [
            'ingresos' => ['cargas' => (int) ($ingresos['cargas'] ?? 0), 'bultos' => (int) ($ingresos['bultos'] ?? 0), 'peso' => (float) ($ingresos['peso'] ?? 0), 'valor' => (float) ($ingresos['valor'] ?? 0)],
            'recepciones' => ['cargas' => (int) ($recepciones['cargas'] ?? 0), 'horasPromedio' => $recepciones['horas_promedio'] !== null ? round((float) $recepciones['horas_promedio'], 1) : null],
            'despachos' => ['salidas' => (int) ($despachos['salidas'] ?? 0), 'contenedores' => (int) ($despachos['contenedores'] ?? 0), 'bultos' => (int) ($despachos['bultos'] ?? 0)],
            'inventario' => ['cargas' => (int) ($inventario['cargas'] ?? 0), 'clientes' => (int) ($inventario['clientes'] ?? 0), 'bultos' => (int) ($inventario['bultos'] ?? 0), 'peso' => (float) ($inventario['peso'] ?? 0), 'valor' => (float) ($inventario['valor'] ?? 0)],
            'porEscanear' => ['cargas' => (int) ($porEscanear['cargas'] ?? 0), 'bultos' => (int) ($porEscanear['bultos'] ?? 0)],
            'sinEtiquetas' => ['cargas' => (int) ($sinEtiquetas['cargas'] ?? 0), 'bultos' => (int) ($sinEtiquetas['bultos'] ?? 0)],
            'antiguedad' => $antiguedad + ['0-30' => ['cargas' => 0, 'bultos' => 0], '31-60' => ['cargas' => 0, 'bultos' => 0], '61-90' => ['cargas' => 0, 'bultos' => 0], '+90' => ['cargas' => 0, 'bultos' => 0]],
            'topClientes' => $topClientes,
            'incompletas' => $incompletas,
        ];
    }
}
