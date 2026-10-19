<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Persistence;

use Courier\Carga\Domain\ConteoBultosCargaInterface;
use PDO;

/** Conteo de lectura sobre lpns y salidas confirmadas (sin cargar agregados). */
final class PdoConteoBultosCarga implements ConteoBultosCargaInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function contar(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(l.estado <> 'receiving'), 0) AS recibidos,
                    COALESCE(SUM(EXISTS (
                        SELECT 1 FROM salida_lineas sl
                        JOIN salida_ordenes so ON so.id = sl.salida_orden_id AND so.estado = 'confirmada'
                        WHERE sl.lpn_id = l.id
                    )), 0) AS despachados
             FROM lpns l
             WHERE l.carga_id = :carga_id"
        );
        $stmt->execute([':carga_id' => $cargaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($fila['total'] ?? 0),
            'recibidos' => (int) ($fila['recibidos'] ?? 0),
            'despachados' => (int) ($fila['despachados'] ?? 0),
        ];
    }
}
