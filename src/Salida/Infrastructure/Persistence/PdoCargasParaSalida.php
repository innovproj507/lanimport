<?php

declare(strict_types=1);

namespace Courier\Salida\Infrastructure\Persistence;

use Courier\Salida\Domain\CargasParaSalidaInterface;
use PDO;

final class PdoCargasParaSalida implements CargasParaSalidaInterface
{
    private const PIE3_A_M3 = 0.028316846592;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function listar(): array
    {
        $filas = $this->connection->query(
            "SELECT c.id, c.tracking_numero, c.cliente_id, c.proveedor, c.fecha_ingreso,
                    TRIM(CONCAT(cl.nombre, ' ', COALESCE(cl.apellido, ''))) AS cliente,
                    DATEDIFF(CURDATE(), c.fecha_ingreso) AS dias,
                    li.marca, COALESCE(li.bultos, 0) AS bultos, COALESCE(li.peso, 0) AS peso,
                    COALESCE(li.valor, 0) AS valor, COALESCE(li.pies3, 0) AS pies3,
                    COALESCE(lp.etiquetas, 0) AS etiquetas, COALESCE(lp.listos, 0) AS listos,
                    COALESCE(lp.por_escanear, 0) AS por_escanear, COALESCE(lp.en_otra_salida, 0) AS en_otra_salida,
                    COALESCE(lp.en_reempaque, 0) AS en_reempaque, COALESCE(lp.en_caja, 0) AS en_caja
             FROM cargas c
             JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN (SELECT carga_id, MAX(marca) AS marca, SUM(cantidad) AS bultos, SUM(peso) AS peso,
                               SUM(valor) AS valor,
                               -- El p3 de la linea es su total (como peso y valor).
                               SUM(COALESCE(cubicaje_p3, ancho * alto * largo * cantidad / 28316.846592)) AS pies3
                        FROM carga_lineas GROUP BY carga_id) li ON li.carga_id = c.id
             LEFT JOIN (SELECT carga_id, COUNT(*) AS etiquetas,
                               SUM(estado = 'available') AS listos,
                               SUM(estado = 'receiving') AS por_escanear,
                               SUM(estado = 'reserved') AS en_otra_salida,
                               SUM(estado = 'in_repack') AS en_reempaque,
                               -- Reempacados en una caja que todavia no sale.
                               SUM(estado = 'consumed' AND EXISTS (SELECT 1 FROM lpn_genealogia g WHERE g.lpn_origen_id = lpns.id)) AS en_caja
                        FROM lpns GROUP BY carga_id) lp ON lp.carga_id = c.id
             WHERE c.estado IN ('ingresado', 'en_bodega')"
        )->fetchAll(PDO::FETCH_ASSOC);

        $cargas = array_map(function (array $f): array {
            $bultos = (int) $f['bultos'];
            $listos = (int) $f['listos'];
            // Lo que sale es la parte escaneada: kilos, valor y m3 en proporcion (como el manifiesto).
            $proporcion = $bultos > 0 ? min(1, $listos / $bultos) : 0;

            return [
                'cargaId' => (string) $f['id'],
                'tracking' => (string) $f['tracking_numero'],
                'clienteId' => (string) $f['cliente_id'],
                'cliente' => (string) $f['cliente'],
                'marca' => $f['marca'] !== null ? (string) $f['marca'] : null,
                'proveedor' => (string) $f['proveedor'],
                'fechaIngreso' => (string) $f['fecha_ingreso'],
                'dias' => max(0, (int) $f['dias']),
                'bultos' => $bultos,
                'etiquetas' => (int) $f['etiquetas'],
                'listos' => $listos,
                'porEscanear' => (int) $f['por_escanear'],
                'enOtraSalida' => (int) $f['en_otra_salida'],
                'enReempaque' => (int) $f['en_reempaque'],
                'enCaja' => (int) $f['en_caja'],
                'peso' => round((float) $f['peso'] * $proporcion, 2),
                'valor' => round((float) $f['valor'] * $proporcion, 2),
                'm3' => round((float) $f['pies3'] * self::PIE3_A_M3 * $proporcion, 3),
                'pesoTotal' => round((float) $f['peso'], 2),
                'valorTotal' => round((float) $f['valor'], 2),
            ];
        }, $filas);

        // Primero las que tienen bultos listos; dentro de cada grupo, cliente y antiguedad.
        usort($cargas, static function (array $a, array $b): int {
            $porListos = ($b['listos'] > 0) <=> ($a['listos'] > 0);

            return $porListos !== 0 ? $porListos : [$a['cliente'], $b['dias']] <=> [$b['cliente'], $a['dias']];
        });

        return $cargas;
    }

    public function cajasListas(): array
    {
        $cajas = $this->connection->query(
            "SELECT l.id, l.codigo, l.cliente_id, l.marca, l.created_at,
                    TRIM(CONCAT(cl.nombre, ' ', COALESCE(cl.apellido, ''))) AS cliente,
                    DATEDIFF(CURDATE(), l.created_at) AS dias
             FROM lpns l
             JOIN clientes cl ON cl.id = l.cliente_id
             WHERE l.carga_id IS NULL AND l.estado = 'available'
             ORDER BY cliente, l.codigo"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Lo que lleva cada caja: sus bultos originales (a cualquier nivel de reempaque), con su
        // parte de los kilos, valor y m3 de su carga.
        $contenido = $this->connection->prepare(
            "WITH RECURSIVE contenido (lpn_id) AS (
                 SELECT l0.id FROM lpns l0 WHERE l0.id = :caja
                 UNION
                 SELECT g.lpn_origen_id FROM contenido x JOIN lpn_genealogia g ON g.lpn_destino_id = x.lpn_id
             )
             SELECT ca.tracking_numero,
                    t.peso / NULLIF(t.bultos, 0) AS peso_bulto,
                    t.valor / NULLIF(t.bultos, 0) AS valor_bulto,
                    t.pies3 / NULLIF(t.bultos, 0) AS pies3_bulto
             FROM contenido x
             JOIN lpns l ON l.id = x.lpn_id AND l.carga_id IS NOT NULL
             JOIN cargas ca ON ca.id = l.carga_id
             JOIN (SELECT carga_id, SUM(cantidad) AS bultos, SUM(peso) AS peso, SUM(valor) AS valor,
                          SUM(COALESCE(cubicaje_p3, ancho * alto * largo * cantidad / 28316.846592)) AS pies3
                   FROM carga_lineas GROUP BY carga_id) t ON t.carga_id = ca.id"
        );

        return array_map(function (array $c) use ($contenido): array {
            $contenido->execute([':caja' => $c['id']]);
            $bultos = $contenido->fetchAll(PDO::FETCH_ASSOC);

            return [
                'lpnId' => (string) $c['id'],
                'codigo' => (string) $c['codigo'],
                'clienteId' => (string) $c['cliente_id'],
                'cliente' => (string) $c['cliente'],
                'marca' => $c['marca'] !== null ? (string) $c['marca'] : null,
                'dias' => max(0, (int) $c['dias']),
                'bultos' => count($bultos),
                'cargas' => array_values(array_unique(array_column($bultos, 'tracking_numero'))),
                'peso' => round(array_sum(array_map(static fn ($b) => (float) $b['peso_bulto'], $bultos)), 2),
                'valor' => round(array_sum(array_map(static fn ($b) => (float) $b['valor_bulto'], $bultos)), 2),
                'm3' => round(array_sum(array_map(static fn ($b) => (float) $b['pies3_bulto'], $bultos)) * self::PIE3_A_M3, 3),
            ];
        }, $cajas);
    }

    public function lpnsListos(array $cargaIds): array
    {
        if ($cargaIds === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($cargaIds), '?'));
        $stmt = $this->connection->prepare(
            "SELECT id FROM lpns WHERE estado = 'available' AND carga_id IN ({$marcas}) ORDER BY carga_id, orden_en_carga"
        );
        $stmt->execute(array_values($cargaIds));

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function lpnsListosDetalle(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT l.id, l.codigo, l.orden_en_carga, u.codigo AS ubicacion,
                    (SELECT COUNT(*) FROM lpns t WHERE t.carga_id = l.carga_id) AS total
             FROM lpns l
             LEFT JOIN ubicaciones u ON u.id = l.ubicacion_id
             WHERE l.carga_id = :carga AND l.estado = 'available'
             ORDER BY l.orden_en_carga, l.codigo"
        );
        $stmt->execute([':carga' => $cargaId]);

        return array_map(static fn (array $f) => [
            'id' => (string) $f['id'],
            'codigo' => (string) $f['codigo'],
            'numero' => $f['orden_en_carga'] !== null ? (int) $f['orden_en_carga'] : null,
            'total' => (int) $f['total'],
            'ubicacion' => $f['ubicacion'] !== null ? (string) $f['ubicacion'] : null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function lpnsListosPorCarga(array $cargaIds): array
    {
        if ($cargaIds === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($cargaIds), '?'));
        $stmt = $this->connection->prepare(
            "SELECT carga_id, id FROM lpns WHERE estado = 'available' AND carga_id IN ({$marcas}) ORDER BY carga_id, orden_en_carga, codigo"
        );
        $stmt->execute(array_values($cargaIds));

        $porCarga = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porCarga[(string) $fila['carga_id']][] = (string) $fila['id'];
        }

        return $porCarga;
    }
}
