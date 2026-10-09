<?php

declare(strict_types=1);

namespace Courier\Reempaque\Infrastructure\Persistence;

use Courier\Reempaque\Domain\GenealogiaLpnRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use PDO;

final class PdoGenealogiaLpnRepository implements GenealogiaLpnRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function registrar(string $reempaqueOrdenId, string $origenLpnId, string $destinoLpnId): void
    {
        $stmt = $this->connection->prepare(
            'INSERT IGNORE INTO lpn_genealogia (id, lpn_origen_id, lpn_destino_id, reempaque_orden_id, created_at)
             VALUES (:id, :lpn_origen_id, :lpn_destino_id, :reempaque_orden_id, NOW())'
        );

        $stmt->execute([
            ':id' => (string) Uuid::generate(),
            ':lpn_origen_id' => $origenLpnId,
            ':lpn_destino_id' => $destinoLpnId,
            ':reempaque_orden_id' => $reempaqueOrdenId,
        ]);
    }

    public function buscarPorLpn(string $lpnId): array
    {
        $ancestros = $this->connection->prepare(
            'SELECT l.id AS lpn_id, l.codigo
             FROM lpn_genealogia g
             INNER JOIN lpns l ON l.id = g.lpn_origen_id
             WHERE g.lpn_destino_id = :lpn_id'
        );
        $ancestros->execute([':lpn_id' => $lpnId]);

        $descendientes = $this->connection->prepare(
            'SELECT l.id AS lpn_id, l.codigo
             FROM lpn_genealogia g
             INNER JOIN lpns l ON l.id = g.lpn_destino_id
             WHERE g.lpn_origen_id = :lpn_id'
        );
        $descendientes->execute([':lpn_id' => $lpnId]);

        $map = fn (array $row) => ['lpnId' => (string) $row['lpn_id'], 'codigo' => (string) $row['codigo']];

        return [
            'ancestros' => array_map($map, $ancestros->fetchAll()),
            'descendientes' => array_map($map, $descendientes->fetchAll()),
        ];
    }
}
