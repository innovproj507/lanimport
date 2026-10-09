<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Persistence;

use Courier\Carga\Domain\CargaStatus;
use Courier\Carga\Domain\HistorialEstado;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoHistorialEstadoRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function append(string $cargaId, HistorialEstado $entry): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO tracking_historial (id, carga_id, estado, comentario, usuario_id, fecha)
             VALUES (:id, :carga_id, :estado, :comentario, :usuario_id, :fecha)'
        );

        $stmt->execute([
            ':id' => $entry->id(),
            ':carga_id' => $cargaId,
            ':estado' => $entry->estado()->value,
            ':comentario' => $entry->comentario(),
            ':usuario_id' => $entry->usuarioId(),
            ':fecha' => $entry->fecha()->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int, HistorialEstado> */
    public function findByCargaId(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM tracking_historial WHERE carga_id = :carga_id ORDER BY fecha ASC'
        );
        $stmt->execute([':carga_id' => $cargaId]);

        $resultado = [];

        foreach ($stmt->fetchAll() as $row) {
            $resultado[] = new HistorialEstado(
                new Uuid((string) $row['id']),
                CargaStatus::from((string) $row['estado']),
                (string) $row['usuario_id'],
                $row['comentario'] !== null ? (string) $row['comentario'] : null,
                new DateTimeImmutable((string) $row['fecha']),
            );
        }

        return $resultado;
    }
}
