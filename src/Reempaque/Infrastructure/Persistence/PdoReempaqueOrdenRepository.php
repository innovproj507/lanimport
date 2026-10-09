<?php

declare(strict_types=1);

namespace Courier\Reempaque\Infrastructure\Persistence;

use Courier\Reempaque\Domain\EstadoReempaqueOrden;
use Courier\Reempaque\Domain\ReempaqueOrden;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Reempaque\Domain\TipoReempaque;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoReempaqueOrdenRepository implements ReempaqueOrdenRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(ReempaqueOrden $orden): void
    {
        if ($this->findHeader($orden->id()) === null) {
            $this->insert($orden);

            return;
        }

        $this->update($orden);
    }

    private function insert(ReempaqueOrden $orden): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO reempaque_ordenes (
                id, tipo, cliente_id, estado, creado_por_usuario_id, created_at, completed_at
            ) VALUES (
                :id, :tipo, :cliente_id, :estado, :creado_por_usuario_id, :created_at, :completed_at
            )'
        );

        $stmt->execute([
            ':id' => $orden->id(),
            ':tipo' => $orden->tipo()->value,
            ':cliente_id' => $orden->clienteId(),
            ':estado' => $orden->estado()->value,
            ':creado_por_usuario_id' => $orden->creadoPorUsuarioId(),
            ':created_at' => $orden->createdAt()->format('Y-m-d H:i:s'),
            ':completed_at' => $orden->completedAt()?->format('Y-m-d H:i:s'),
        ]);

        $secuencia = (int) $this->connection->lastInsertId();
        $codigo = 'REP-' . str_pad((string) $secuencia, 6, '0', STR_PAD_LEFT);

        $update = $this->connection->prepare('UPDATE reempaque_ordenes SET codigo = :codigo WHERE id = :id');
        $update->execute([':codigo' => $codigo, ':id' => $orden->id()]);

        $this->guardarLpns($orden->id(), $orden->origenLpnIds(), 'origen');
    }

    private function update(ReempaqueOrden $orden): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE reempaque_ordenes SET estado = :estado, completed_at = :completed_at WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $orden->id(),
            ':estado' => $orden->estado()->value,
            ':completed_at' => $orden->completedAt()?->format('Y-m-d H:i:s'),
        ]);

        if ($orden->estado() === EstadoReempaqueOrden::COMPLETADO) {
            $this->guardarLpns($orden->id(), $orden->destinoLpnIds(), 'destino');
        }
    }

    /** @param array<int, string> $lpnIds */
    private function guardarLpns(string $ordenId, array $lpnIds, string $rol): void
    {
        $stmt = $this->connection->prepare(
            'INSERT IGNORE INTO reempaque_lpns (id, reempaque_orden_id, lpn_id, rol)
             VALUES (:id, :reempaque_orden_id, :lpn_id, :rol)'
        );

        foreach ($lpnIds as $lpnId) {
            $stmt->execute([
                ':id' => (string) Uuid::generate(),
                ':reempaque_orden_id' => $ordenId,
                ':lpn_id' => $lpnId,
                ':rol' => $rol,
            ]);
        }
    }

    public function findById(string $id): ?ReempaqueOrden
    {
        $row = $this->findHeader($id);

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function search(array $filtros): array
    {
        $where = [];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[] = 'estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['clienteId'])) {
            $where[] = 'cliente_id = :cliente_id';
            $params[':cliente_id'] = $filtros['clienteId'];
        }

        $sql = 'SELECT * FROM reempaque_ordenes';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY secuencia DESC';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    /** @return array<string, mixed>|null */
    private function findHeader(string $id): ?array
    {
        $stmt = $this->connection->prepare('SELECT * FROM reempaque_ordenes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<int, string> */
    private function buscarLpnIds(string $ordenId, string $rol): array
    {
        $stmt = $this->connection->prepare(
            'SELECT lpn_id FROM reempaque_lpns WHERE reempaque_orden_id = :reempaque_orden_id AND rol = :rol'
        );
        $stmt->execute([':reempaque_orden_id' => $ordenId, ':rol' => $rol]);

        return array_map(fn (array $row) => (string) $row['lpn_id'], $stmt->fetchAll());
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ReempaqueOrden
    {
        $ordenId = (string) $row['id'];

        return new ReempaqueOrden(
            new Uuid($ordenId),
            TipoReempaque::from((string) $row['tipo']),
            (string) $row['cliente_id'],
            EstadoReempaqueOrden::from((string) $row['estado']),
            $this->buscarLpnIds($ordenId, 'origen'),
            $this->buscarLpnIds($ordenId, 'destino'),
            (string) $row['creado_por_usuario_id'],
            new DateTimeImmutable((string) $row['created_at']),
            $row['completed_at'] !== null ? new DateTimeImmutable((string) $row['completed_at']) : null,
            $row['codigo'] !== null ? (string) $row['codigo'] : null,
            $row['secuencia'] !== null ? (int) $row['secuencia'] : null,
        );
    }
}
