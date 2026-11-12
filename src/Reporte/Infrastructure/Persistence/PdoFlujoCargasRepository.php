<?php

declare(strict_types=1);

namespace Courier\Reporte\Infrastructure\Persistence;

use PDO;

/**
 * Datos para el "Flujo de cargas" de Reportes: cada carga con sus totales y etiquetas,
 * y las salidas (DO) en las que estan sus bultos.
 */
final class PdoFlujoCargasRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * Todas las cargas (filtradas por aduanero y/o cliente) con bultos, peso y p3 de sus lineas,
     * cuantas etiquetas tienen y cuantas faltan por escanear en recepcion.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cargas(?string $aduanero, ?string $clienteId): array
    {
        $where = [];
        $params = [];

        if ($aduanero !== null) {
            $where[] = 'c.aduanero_identificador = :aduanero';
            $params[':aduanero'] = $aduanero;
        }

        if ($clienteId !== null) {
            $where[] = 'c.cliente_id = :cliente';
            $params[':cliente'] = $clienteId;
        }

        // p3: total de la linea; si no se escribio, W x H x L x cantidad / 28316.85 (igual que LineaCarga::cubicajePies()).
        $sql = "SELECT c.id, c.tracking_numero, c.estado, c.fecha_ingreso, c.created_at, c.updated_at,
                       TRIM(CONCAT(COALESCE(cl.nombre, ''), ' ', COALESCE(cl.apellido, ''))) AS cliente,
                       COALESCE(a.nombre, '') AS aduanero,
                       COALESCE(l.bultos, 0) AS bultos, COALESCE(l.peso, 0) AS peso, COALESCE(l.p3, 0) AS p3, COALESCE(l.valor, 0) AS valor,
                       COALESCE(e.total, 0) AS etiquetas, COALESCE(e.por_escanear, 0) AS por_escanear,
                       h.en_bodega_at, h.en_transito_at
                FROM cargas c
                LEFT JOIN clientes cl ON cl.id = c.cliente_id
                LEFT JOIN aduaneros a ON a.identificador = c.aduanero_identificador
                LEFT JOIN (SELECT carga_id, SUM(cantidad) AS bultos, SUM(peso) AS peso, SUM(valor) AS valor,
                                  SUM(CASE WHEN cubicaje_p3 > 0 THEN cubicaje_p3
                                           ELSE COALESCE(ancho, 0) * COALESCE(alto, 0) * COALESCE(largo, 0) * cantidad / 28316.85 END) AS p3
                           FROM carga_lineas GROUP BY carga_id) l ON l.carga_id = c.id
                LEFT JOIN (SELECT carga_id, COUNT(*) AS total, SUM(estado = 'receiving') AS por_escanear
                           FROM lpns WHERE carga_id IS NOT NULL GROUP BY carga_id) e ON e.carga_id = c.id
                LEFT JOIN (SELECT carga_id,
                                  MAX(CASE WHEN estado = 'en_bodega' THEN fecha END) AS en_bodega_at,
                                  MAX(CASE WHEN estado = 'en_transito' THEN fecha END) AS en_transito_at
                           FROM tracking_historial GROUP BY carga_id) h ON h.carga_id = c.id"
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '');

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Por carga: las salidas (pendientes o confirmadas) que llevan sus bultos, directos o dentro
     * de una caja de reempaque (lpn_genealogia), con cuantos bultos van y cuantos ya se escanearon.
     *
     * @return array<string, array<int, array<string, mixed>>> cargaId => salidas
     */
    public function salidasPorCarga(): array
    {
        $stmt = $this->connection->query(
            "SELECT x.carga_id, so.id, so.do_anio, so.do_numero, so.codigo, so.estado, so.numero_contenedor,
                    so.numero_sello, so.nombre_barco, so.fecha_cargue, so.fecha_salida_etd, so.destino_final,
                    so.confirmed_at, so.created_at, n.nombre AS naviera,
                    COUNT(*) AS bultos, SUM(sl.escaneado) AS escaneados
             FROM (SELECT lp.carga_id, lp.id AS lpn_salida FROM lpns lp WHERE lp.carga_id IS NOT NULL
                   UNION ALL
                   SELECT lp.carga_id, g.lpn_destino_id FROM lpns lp
                   JOIN lpn_genealogia g ON g.lpn_origen_id = lp.id
                   WHERE lp.carga_id IS NOT NULL) x
             JOIN salida_lineas sl ON sl.lpn_id = x.lpn_salida
             JOIN salida_ordenes so ON so.id = sl.salida_orden_id AND so.estado IN ('pendiente', 'confirmada')
             LEFT JOIN navieras n ON n.id = so.naviera_id
             GROUP BY x.carga_id, so.id, so.do_anio, so.do_numero, so.codigo, so.estado, so.numero_contenedor,
                      so.numero_sello, so.nombre_barco, so.fecha_cargue, so.fecha_salida_etd, so.destino_final,
                      so.confirmed_at, so.created_at, n.nombre
             ORDER BY so.created_at"
        );

        $porCarga = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porCarga[(string) $fila['carga_id']][] = $fila;
        }

        return $porCarga;
    }
}
