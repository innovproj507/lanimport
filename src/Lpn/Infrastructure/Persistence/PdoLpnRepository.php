<?php

declare(strict_types=1);

namespace Courier\Lpn\Infrastructure\Persistence;

use Courier\Lpn\Domain\EstadoLpn;
use Courier\Lpn\Domain\Lpn;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoLpnRepository implements LpnRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Lpn $lpn): void
    {
        if ($this->findById($lpn->id()) === null) {
            $this->insert($lpn);

            return;
        }

        $this->update($lpn);
    }

    private function insert(Lpn $lpn): void
    {
        if ($lpn->codigo() !== null) {
            $stmt = $this->connection->prepare(
                'INSERT INTO lpns (id, carga_id, cliente_id, marca, descripcion, orden_en_carga, codigo, estado, ubicacion_id, created_at, updated_at)
                 VALUES (:id, :carga_id, :cliente_id, :marca, :descripcion, :orden_en_carga, :codigo, :estado, :ubicacion_id, :created_at, :updated_at)'
            );

            $stmt->execute([
                ':id' => $lpn->id(),
                ':carga_id' => $lpn->cargaId(),
                ':cliente_id' => $lpn->clienteId(),
                ':marca' => $lpn->marca(),
                ':descripcion' => $lpn->descripcion(),
                ':orden_en_carga' => $lpn->ordenEnCarga(),
                ':codigo' => $lpn->codigo(),
                ':estado' => $lpn->estado()->value,
                ':ubicacion_id' => $lpn->ubicacionId(),
                ':created_at' => $lpn->createdAt()->format('Y-m-d H:i:s'),
                ':updated_at' => $lpn->updatedAt()->format('Y-m-d H:i:s'),
            ]);

            return;
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO lpns (id, carga_id, cliente_id, marca, descripcion, orden_en_carga, estado, ubicacion_id, created_at, updated_at)
             VALUES (:id, :carga_id, :cliente_id, :marca, :descripcion, :orden_en_carga, :estado, :ubicacion_id, :created_at, :updated_at)'
        );

        $stmt->execute([
            ':id' => $lpn->id(),
            ':carga_id' => $lpn->cargaId(),
            ':cliente_id' => $lpn->clienteId(),
            ':marca' => $lpn->marca(),
            ':descripcion' => $lpn->descripcion(),
            ':orden_en_carga' => $lpn->ordenEnCarga(),
            ':estado' => $lpn->estado()->value,
            ':ubicacion_id' => $lpn->ubicacionId(),
            ':created_at' => $lpn->createdAt()->format('Y-m-d H:i:s'),
            ':updated_at' => $lpn->updatedAt()->format('Y-m-d H:i:s'),
        ]);

        $secuencia = (int) $this->connection->lastInsertId();
        $codigo = 'LPN-' . str_pad((string) $secuencia, 6, '0', STR_PAD_LEFT);

        $update = $this->connection->prepare('UPDATE lpns SET codigo = :codigo WHERE id = :id');
        $update->execute([':codigo' => $codigo, ':id' => $lpn->id()]);
    }

    private function update(Lpn $lpn): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE lpns SET estado = :estado, ubicacion_id = :ubicacion_id, updated_at = :updated_at WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $lpn->id(),
            ':estado' => $lpn->estado()->value,
            ':ubicacion_id' => $lpn->ubicacionId(),
            ':updated_at' => $lpn->updatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Lpn
    {
        $stmt = $this->connection->prepare('SELECT * FROM lpns WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCodigo(string $codigo): ?Lpn
    {
        $stmt = $this->connection->prepare('SELECT * FROM lpns WHERE codigo = :codigo LIMIT 1');
        $stmt->execute([':codigo' => $codigo]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCargaId(string $cargaId): array
    {
        $stmt = $this->connection->prepare('SELECT * FROM lpns WHERE carga_id = :carga_id ORDER BY secuencia ASC');
        $stmt->execute([':carga_id' => $cargaId]);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function existsLpnsParaCarga(string $cargaId): bool
    {
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM lpns WHERE carga_id = :carga_id');
        $stmt->execute([':carga_id' => $cargaId]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function deleteByCargaId(string $cargaId): void
    {
        $stmt = $this->connection->prepare('DELETE FROM lpns WHERE carga_id = :carga_id');
        $stmt->execute([':carga_id' => $cargaId]);
    }

    public function search(array $filtros): array
    {
        $where = [];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[] = 'l.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['ubicacionId'])) {
            $where[] = 'l.ubicacion_id = :ubicacion_id';
            $params[':ubicacion_id'] = $filtros['ubicacionId'];
        }

        if (!empty($filtros['clienteId'])) {
            $where[] = 'l.cliente_id = :cliente_id';
            $params[':cliente_id'] = $filtros['clienteId'];
        }

        if (!empty($filtros['cargaCodigo'])) {
            $where[] = 'c.tracking_numero LIKE :carga_codigo';
            $params[':carga_codigo'] = '%' . $filtros['cargaCodigo'] . '%';
        }

        if (!empty($filtros['aduaneroIdentificador'])) {
            $where[] = 'c.aduanero_identificador = :aduanero_identificador';
            $params[':aduanero_identificador'] = $filtros['aduaneroIdentificador'];
        }

        $sql = 'SELECT l.* FROM lpns l LEFT JOIN cargas c ON c.id = l.carga_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY l.secuencia DESC';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Lpn
    {
        return new Lpn(
            new Uuid((string) $row['id']),
            $row['carga_id'] !== null ? (string) $row['carga_id'] : null,
            (string) $row['cliente_id'],
            EstadoLpn::from((string) $row['estado']),
            $row['ubicacion_id'] !== null ? (string) $row['ubicacion_id'] : null,
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
            $row['codigo'] !== null ? (string) $row['codigo'] : null,
            $row['secuencia'] !== null ? (int) $row['secuencia'] : null,
            $row['marca'] !== null ? (string) $row['marca'] : null,
            $row['descripcion'] !== null ? (string) $row['descripcion'] : null,
            $row['orden_en_carga'] !== null ? (int) $row['orden_en_carga'] : null,
        );
    }
}
