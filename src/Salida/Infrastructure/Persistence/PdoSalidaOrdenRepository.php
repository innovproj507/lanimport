<?php

declare(strict_types=1);

namespace Courier\Salida\Infrastructure\Persistence;

use Courier\Salida\Domain\EstadoSalidaOrden;
use Courier\Salida\Domain\SalidaLinea;
use Courier\Salida\Domain\SalidaOrden;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoSalidaOrdenRepository implements SalidaOrdenRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(SalidaOrden $orden): void
    {
        if ($this->findHeader($orden->id()) === null) {
            $this->insert($orden);

            return;
        }

        $this->update($orden);
    }

    private function insert(SalidaOrden $orden): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO salida_ordenes (
                id, cliente_id, aduanero_identificador, estado, creado_por_usuario_id, created_at, confirmed_at
            ) VALUES (
                :id, :cliente_id, :aduanero_identificador, :estado, :creado_por_usuario_id, :created_at, :confirmed_at
            )'
        );

        $stmt->execute([
            ':id' => $orden->id(),
            ':cliente_id' => $orden->clienteId(),
            ':aduanero_identificador' => $orden->aduaneroIdentificador(),
            ':estado' => $orden->estado()->value,
            ':creado_por_usuario_id' => $orden->creadoPorUsuarioId(),
            ':created_at' => $orden->createdAt()->format('Y-m-d H:i:s'),
            ':confirmed_at' => $orden->confirmedAt()?->format('Y-m-d H:i:s'),
        ]);

        $secuencia = (int) $this->connection->lastInsertId();
        $codigo = 'SAL-' . str_pad((string) $secuencia, 6, '0', STR_PAD_LEFT);

        $update = $this->connection->prepare('UPDATE salida_ordenes SET codigo = :codigo WHERE id = :id');
        $update->execute([':codigo' => $codigo, ':id' => $orden->id()]);

        $insertLinea = $this->connection->prepare(
            'INSERT INTO salida_lineas (id, salida_orden_id, lpn_id, escaneado, escaneado_at)
             VALUES (:id, :salida_orden_id, :lpn_id, :escaneado, :escaneado_at)'
        );

        foreach ($orden->lineas() as $linea) {
            $insertLinea->execute([
                ':id' => (string) Uuid::generate(),
                ':salida_orden_id' => $orden->id(),
                ':lpn_id' => $linea->lpnId(),
                ':escaneado' => $linea->escaneado() ? 1 : 0,
                ':escaneado_at' => $linea->escaneadoAt()?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function update(SalidaOrden $orden): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE salida_ordenes SET estado = :estado, confirmed_at = :confirmed_at WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $orden->id(),
            ':estado' => $orden->estado()->value,
            ':confirmed_at' => $orden->confirmedAt()?->format('Y-m-d H:i:s'),
        ]);

        $upsertLinea = $this->connection->prepare(
            'UPDATE salida_lineas SET escaneado = :escaneado, escaneado_at = :escaneado_at
             WHERE salida_orden_id = :salida_orden_id AND lpn_id = :lpn_id'
        );

        foreach ($orden->lineas() as $linea) {
            $upsertLinea->execute([
                ':escaneado' => $linea->escaneado() ? 1 : 0,
                ':escaneado_at' => $linea->escaneadoAt()?->format('Y-m-d H:i:s'),
                ':salida_orden_id' => $orden->id(),
                ':lpn_id' => $linea->lpnId(),
            ]);
        }
    }

    public function findById(string $id): ?SalidaOrden
    {
        $row = $this->findHeader($id);

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row, $this->hydrarLineas($id));
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

        $sql = 'SELECT * FROM salida_ordenes';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY secuencia DESC';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return array_map(
            fn (array $row) => $this->hydrate($row, $this->hydrarLineas((string) $row['id'])),
            $stmt->fetchAll(),
        );
    }

    /** @return array<string, mixed>|null */
    private function findHeader(string $id): ?array
    {
        $stmt = $this->connection->prepare('SELECT * FROM salida_ordenes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<int, SalidaLinea> */
    private function hydrarLineas(string $ordenId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT sl.lpn_id, sl.escaneado, sl.escaneado_at, l.codigo
             FROM salida_lineas sl
             INNER JOIN lpns l ON l.id = sl.lpn_id
             WHERE sl.salida_orden_id = :salida_orden_id'
        );
        $stmt->execute([':salida_orden_id' => $ordenId]);

        return array_map(
            fn (array $row) => new SalidaLinea(
                (string) $row['lpn_id'],
                (string) $row['codigo'],
                ((int) $row['escaneado']) === 1,
                $row['escaneado_at'] !== null ? new DateTimeImmutable((string) $row['escaneado_at']) : null,
            ),
            $stmt->fetchAll(),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, SalidaLinea> $lineas
     */
    private function hydrate(array $row, array $lineas): SalidaOrden
    {
        return new SalidaOrden(
            new Uuid((string) $row['id']),
            (string) $row['cliente_id'],
            $row['aduanero_identificador'] !== null ? (string) $row['aduanero_identificador'] : null,
            EstadoSalidaOrden::from((string) $row['estado']),
            (string) $row['creado_por_usuario_id'],
            new DateTimeImmutable((string) $row['created_at']),
            $row['confirmed_at'] !== null ? new DateTimeImmutable((string) $row['confirmed_at']) : null,
            $lineas,
            $row['codigo'] !== null ? (string) $row['codigo'] : null,
            $row['secuencia'] !== null ? (int) $row['secuencia'] : null,
        );
    }
}
