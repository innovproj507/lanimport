<?php

declare(strict_types=1);

namespace Courier\Salida\Infrastructure\Persistence;

use Courier\Salida\Domain\RastreoCargaInterface;
use PDO;

final class PdoRastreoCarga implements RastreoCargaInterface
{
    /**
     * Bultos de la carga y el LPN con que viajan: el propio, o la caja de reempaque que lo contiene.
     */
    private const BULTOS_Y_CAJAS = "SELECT l.id AS bulto_id, l.id AS viaja_id FROM lpns l WHERE l.carga_id = :c1
        UNION ALL
        SELECT g.lpn_origen_id, g.lpn_destino_id FROM lpn_genealogia g JOIN lpns o ON o.id = g.lpn_origen_id WHERE o.carga_id = :c2";

    public function __construct(private readonly PDO $connection)
    {
    }

    public function detalle(string $cargaId): array
    {
        return [
            'carga' => $this->carga($cargaId),
            'ordenes' => $this->ordenes($cargaId),
            'bultos' => $this->bultos($cargaId),
        ];
    }

    /** @return array<string, mixed> */
    private function carga(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT TRIM(CONCAT(COALESCE(cl.nombre, ''), ' ', COALESCE(cl.apellido, ''))) AS cliente,
                    COALESCE(a.nombre, c.aduanero_nombre, c.aduanero_identificador, '') AS aduanero,
                    COALESCE(p.nombre, c.proveedor, '') AS proveedor,
                    u.nombre AS registrado_por, c.fecha_ingreso, c.numero_contenedor, c.notas
             FROM cargas c
             LEFT JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN aduaneros a ON a.identificador = c.aduanero_identificador
             LEFT JOIN proveedores p ON p.identificador = c.proveedor_identificador
             LEFT JOIN usuarios u ON u.id = c.registrado_por_usuario_id
             WHERE c.id = :id"
        );
        $stmt->execute([':id' => $cargaId]);
        $r = $stmt->fetch() ?: [];

        return [
            'cliente' => (string) ($r['cliente'] ?? ''),
            'aduanero' => (string) ($r['aduanero'] ?? ''),
            'proveedor' => (string) ($r['proveedor'] ?? ''),
            'registradoPor' => $r['registrado_por'] ?? null,
            'fechaIngreso' => $r['fecha_ingreso'] ?? null,
            'contenedor' => ($r['numero_contenedor'] ?? '') !== '' ? $r['numero_contenedor'] : null,
            'notas' => ($r['notas'] ?? '') !== '' ? $r['notas'] : null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ordenes(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT so.id, so.codigo, so.do_numero, so.do_anio, so.estado, so.numero_contenedor, so.numero_sello,
                    n.nombre AS naviera, so.nombre_barco, so.fecha_cargue, so.fecha_salida_etd, so.destino_final, so.nota,
                    so.created_at, so.confirmed_at,
                    COUNT(*) AS bultos, SUM(sl.escaneado) AS escaneados
             FROM (" . self::BULTOS_Y_CAJAS . ") b
             JOIN salida_lineas sl ON sl.lpn_id = b.viaja_id
             JOIN salida_ordenes so ON so.id = sl.salida_orden_id
             LEFT JOIN navieras n ON n.id = so.naviera_id
             GROUP BY so.id
             ORDER BY so.created_at DESC"
        );
        $stmt->execute([':c1' => $cargaId, ':c2' => $cargaId]);

        return array_map(static fn (array $r) => [
            'id' => (string) $r['id'],
            'etiqueta' => self::etiqueta($r),
            'esOs' => $r['do_numero'] === null,
            'estado' => (string) $r['estado'],
            'contenedor' => $r['numero_contenedor'] ?: null,
            'sello' => $r['numero_sello'] ?: null,
            'naviera' => $r['naviera'] ?: null,
            'barco' => $r['nombre_barco'] ?: null,
            'fechaCargue' => $r['fecha_cargue'] ?: null,
            'etd' => $r['fecha_salida_etd'] ?: null,
            'destino' => $r['destino_final'] ?: null,
            'nota' => $r['nota'] ?: null,
            'creada' => (string) $r['created_at'],
            'confirmada' => $r['confirmed_at'] ?: null,
            'bultos' => (int) $r['bultos'],
            'escaneados' => (int) $r['escaneados'],
        ], $stmt->fetchAll());
    }

    /** @return array<int, array<string, mixed>> */
    private function bultos(string $cargaId): array
    {
        // Orden vigente (no cancelada) de cada bulto; si estuvo en varias, la mas reciente.
        $stmt = $this->connection->prepare(
            "SELECT b.bulto_id, b.viaja_id, so.id, so.codigo, so.do_numero, so.do_anio, so.estado, sl.escaneado
             FROM (" . self::BULTOS_Y_CAJAS . ") b
             JOIN salida_lineas sl ON sl.lpn_id = b.viaja_id
             JOIN salida_ordenes so ON so.id = sl.salida_orden_id
             WHERE so.estado <> 'cancelada'
             ORDER BY so.created_at"
        );
        $stmt->execute([':c1' => $cargaId, ':c2' => $cargaId]);
        $ordenPorBulto = [];

        foreach ($stmt->fetchAll() as $r) {
            $ordenPorBulto[$r['bulto_id']] = $r;
        }

        $stmt = $this->connection->prepare(
            "SELECT l.id, l.codigo, l.estado, u.codigo AS ubicacion,
                    (SELECT d.codigo FROM lpn_genealogia g JOIN lpns d ON d.id = g.lpn_destino_id
                     WHERE g.lpn_origen_id = l.id ORDER BY g.created_at DESC LIMIT 1) AS caja
             FROM lpns l
             LEFT JOIN ubicaciones u ON u.id = l.ubicacion_id
             WHERE l.carga_id = :c
             ORDER BY l.orden_en_carga, l.codigo"
        );
        $stmt->execute([':c' => $cargaId]);

        return array_map(static function (array $r) use ($ordenPorBulto) {
            $o = $ordenPorBulto[$r['id']] ?? null;

            return [
                'codigo' => (string) $r['codigo'],
                'estado' => (string) $r['estado'],
                'ubicacion' => $r['ubicacion'] ?: null,
                'caja' => $r['caja'] ?: null,
                'ordenId' => $o !== null ? (string) $o['id'] : null,
                'orden' => $o !== null ? self::etiqueta($o) : null,
                'ordenEstado' => $o !== null ? (string) $o['estado'] : null,
                'escaneado' => $o !== null ? (bool) $o['escaneado'] : null,
            ];
        }, $stmt->fetchAll());
    }

    /** @param array<string, mixed> $r */
    private static function etiqueta(array $r): string
    {
        return $r['do_numero'] !== null ? "DO.{$r['do_numero']} / {$r['do_anio']}" : (string) $r['codigo'];
    }
}
