<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Persistence;

use Courier\Carga\Domain\ClienteBultosCargaInterface;
use PDO;

final class PdoClienteBultosCarga implements ClienteBultosCargaInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function reasignar(string $cargaId, string $clienteId): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE lpns SET cliente_id = :cliente_id, updated_at = NOW()
             WHERE carga_id = :carga_id AND cliente_id <> :cliente_id_actual'
        );
        $stmt->execute(['cliente_id' => $clienteId, 'carga_id' => $cargaId, 'cliente_id_actual' => $clienteId]);

        if ($stmt->rowCount() === 0) {
            return;
        }

        // Cada salida con bultos de la carga queda con el cliente unico de sus LPNs, o NULL si es consolidada.
        $this->connection->prepare(
            'UPDATE salida_ordenes so
             JOIN (
                 SELECT sl.salida_orden_id, IF(COUNT(DISTINCT l.cliente_id) = 1, MIN(l.cliente_id), NULL) AS cliente_id
                 FROM salida_lineas sl
                 JOIN lpns l ON l.id = sl.lpn_id
                 WHERE sl.salida_orden_id IN (
                     SELECT sl2.salida_orden_id FROM salida_lineas sl2
                     JOIN lpns l2 ON l2.id = sl2.lpn_id
                     WHERE l2.carga_id = :carga_id
                 )
                 GROUP BY sl.salida_orden_id
             ) t ON t.salida_orden_id = so.id
             SET so.cliente_id = t.cliente_id'
        )->execute(['carga_id' => $cargaId]);
    }
}
