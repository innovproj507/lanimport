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

    public function deleteById(string $id): void
    {
        $this->connection->prepare('DELETE FROM etiquetas WHERE lpn_id = :id')->execute([':id' => $id]);
        $this->connection->prepare('DELETE FROM lpns WHERE id = :id')->execute([':id' => $id]);
    }

    public function cargasContenidas(string $lpnId): array
    {
        return array_values(array_unique(array_column($this->bultosContenidos($lpnId), 'cargaId')));
    }

    public function bultosContenidos(string $lpnId): array
    {
        // Baja por la genealogia (destino -> origen) hasta los bultos que tienen carga.
        $stmt = $this->connection->prepare(
            "WITH RECURSIVE contenido (lpn_id) AS (
                 SELECT l0.id FROM lpns l0 WHERE l0.id = :lpn
                 UNION
                 SELECT g.lpn_origen_id FROM lpn_genealogia g JOIN contenido c ON g.lpn_destino_id = c.lpn_id
             )
             SELECT l.id, l.codigo, l.carga_id, ca.tracking_numero, l.marca
             FROM contenido c
             JOIN lpns l ON l.id = c.lpn_id
             JOIN cargas ca ON ca.id = l.carga_id
             ORDER BY ca.tracking_numero, l.orden_en_carga"
        );
        $stmt->execute([':lpn' => $lpnId]);

        return array_map(static fn (array $f) => [
            'lpnId' => (string) $f['id'],
            'codigo' => (string) $f['codigo'],
            'cargaId' => (string) $f['carga_id'],
            'tracking' => (string) $f['tracking_numero'],
            'marca' => $f['marca'] !== null ? (string) $f['marca'] : null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
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

    public function resumenRecepcion(?string $cargaId = null): array
    {
        $sql = "SELECT c.id, c.tracking_numero, c.fecha_ingreso,
                       TRIM(CONCAT(cl.nombre, ' ', COALESCE(cl.apellido, ''))) AS cliente,
                       MAX(l.marca) AS marca,
                       COUNT(*) AS total,
                       SUM(l.estado <> 'receiving') AS recibidos
                FROM lpns l
                JOIN cargas c ON c.id = l.carga_id
                LEFT JOIN clientes cl ON cl.id = c.cliente_id"
            . ($cargaId !== null ? ' WHERE c.id = :carga_id' : '')
            . " GROUP BY c.id, c.tracking_numero, c.fecha_ingreso, cl.nombre, cl.apellido"
            . ($cargaId !== null ? '' : " HAVING SUM(l.estado = 'receiving') > 0")
            . ' ORDER BY c.fecha_ingreso ASC, c.tracking_numero ASC';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($cargaId !== null ? [':carga_id' => $cargaId] : []);

        return array_map(static fn (array $row) => [
            'cargaId' => (string) $row['id'],
            'tracking' => (string) $row['tracking_numero'],
            'cliente' => (string) $row['cliente'],
            'marca' => $row['marca'] !== null ? (string) $row['marca'] : null,
            'fechaIngreso' => (string) $row['fecha_ingreso'],
            'total' => (int) $row['total'],
            'recibidos' => (int) $row['recibidos'],
        ], $stmt->fetchAll());
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
