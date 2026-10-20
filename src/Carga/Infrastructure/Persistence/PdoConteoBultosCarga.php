<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Persistence;

use Courier\Carga\Domain\ConteoBultosCargaInterface;
use PDO;

/**
 * Conteo de lectura sobre lpns y salidas confirmadas (sin cargar agregados).
 * Un bulto reempacado cuenta como despachado cuando la caja nueva donde va (a cualquier
 * nivel de reempaque) salio en una salida confirmada.
 */
final class PdoConteoBultosCarga implements ConteoBultosCargaInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function contar(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            "WITH RECURSIVE arbol (origen, nodo) AS (
                 SELECT l.id, l.id FROM lpns l WHERE l.carga_id = :carga_id
                 UNION
                 SELECT a.origen, g.lpn_destino_id FROM arbol a JOIN lpn_genealogia g ON g.lpn_origen_id = a.nodo
             ),
             -- Ultimo eslabon de cada bulto: el mismo, o la caja final donde quedo reempacado.
             hojas AS (
                 SELECT a.origen, a.nodo FROM arbol a
                 WHERE NOT EXISTS (SELECT 1 FROM lpn_genealogia g WHERE g.lpn_origen_id = a.nodo)
             ),
             despachadas AS (
                 SELECT DISTINCT sl.lpn_id FROM salida_lineas sl
                 JOIN salida_ordenes so ON so.id = sl.salida_orden_id AND so.estado = 'confirmada'
             )
             SELECT COUNT(*) AS total,
                    COALESCE(SUM(l.estado <> 'receiving'), 0) AS recibidos,
                    COALESCE(SUM(NOT EXISTS (
                        SELECT 1 FROM hojas h WHERE h.origen = l.id AND h.nodo NOT IN (SELECT lpn_id FROM despachadas)
                    )), 0) AS despachados
             FROM lpns l
             WHERE l.carga_id = :carga_id2"
        );
        $stmt->execute([':carga_id' => $cargaId, ':carga_id2' => $cargaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($fila['total'] ?? 0),
            'recibidos' => (int) ($fila['recibidos'] ?? 0),
            'despachados' => (int) ($fila['despachados'] ?? 0),
        ];
    }
}
