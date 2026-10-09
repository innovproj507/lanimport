<?php

declare(strict_types=1);

namespace Courier\Factura\Infrastructure\Persistence;

use Courier\Factura\Domain\EstadoFactura;
use Courier\Factura\Domain\Factura;
use Courier\Factura\Domain\FacturaRepositoryInterface;
use Courier\Factura\Domain\LineaFactura;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoFacturaRepository implements FacturaRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Factura $factura): void
    {
        if ($this->findHeader($factura->id()) === null) {
            $this->insert($factura);

            return;
        }

        $this->update($factura);
    }

    private function insert(Factura $factura): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO facturas (
                id, cliente_id, carga_id, estado, moneda, total, notas, creado_por_usuario_id, created_at, paid_at
            ) VALUES (
                :id, :cliente_id, :carga_id, :estado, :moneda, :total, :notas, :creado_por_usuario_id, :created_at, :paid_at
            )'
        );

        $stmt->execute([
            ':id' => $factura->id(),
            ':cliente_id' => $factura->clienteId(),
            ':carga_id' => $factura->cargaId(),
            ':estado' => $factura->estado()->value,
            ':moneda' => $factura->moneda(),
            ':total' => $factura->total(),
            ':notas' => $factura->notas(),
            ':creado_por_usuario_id' => $factura->creadoPorUsuarioId(),
            ':created_at' => $factura->createdAt()->format('Y-m-d H:i:s'),
            ':paid_at' => $factura->paidAt()?->format('Y-m-d H:i:s'),
        ]);

        $secuencia = (int) $this->connection->lastInsertId();
        $codigo = 'FAC-' . str_pad((string) $secuencia, 6, '0', STR_PAD_LEFT);

        $update = $this->connection->prepare('UPDATE facturas SET codigo = :codigo WHERE id = :id');
        $update->execute([':codigo' => $codigo, ':id' => $factura->id()]);

        $insertLinea = $this->connection->prepare(
            'INSERT INTO lineas_factura (id, factura_id, descripcion, cantidad, precio_unitario, subtotal, orden)
             VALUES (:id, :factura_id, :descripcion, :cantidad, :precio_unitario, :subtotal, :orden)'
        );

        foreach ($factura->lineas() as $orden => $linea) {
            $insertLinea->execute([
                ':id' => (string) Uuid::generate(),
                ':factura_id' => $factura->id(),
                ':descripcion' => $linea->descripcion(),
                ':cantidad' => $linea->cantidad(),
                ':precio_unitario' => $linea->precioUnitario(),
                ':subtotal' => $linea->subtotal(),
                ':orden' => $orden,
            ]);
        }
    }

    private function update(Factura $factura): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE facturas SET estado = :estado, paid_at = :paid_at WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $factura->id(),
            ':estado' => $factura->estado()->value,
            ':paid_at' => $factura->paidAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Factura
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

        $sql = 'SELECT * FROM facturas';

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
        $stmt = $this->connection->prepare('SELECT * FROM facturas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<int, LineaFactura> */
    private function hydrarLineas(string $facturaId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT descripcion, cantidad, precio_unitario FROM lineas_factura
             WHERE factura_id = :factura_id ORDER BY orden ASC'
        );
        $stmt->execute([':factura_id' => $facturaId]);

        return array_map(
            fn (array $row) => new LineaFactura(
                (string) $row['descripcion'],
                (float) $row['cantidad'],
                (float) $row['precio_unitario'],
            ),
            $stmt->fetchAll(),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, LineaFactura> $lineas
     */
    private function hydrate(array $row, array $lineas): Factura
    {
        return new Factura(
            new Uuid((string) $row['id']),
            (string) $row['cliente_id'],
            $row['carga_id'] !== null ? (string) $row['carga_id'] : null,
            EstadoFactura::from((string) $row['estado']),
            (string) $row['moneda'],
            $row['notas'] !== null ? (string) $row['notas'] : null,
            (string) $row['creado_por_usuario_id'],
            new DateTimeImmutable((string) $row['created_at']),
            $row['paid_at'] !== null ? new DateTimeImmutable((string) $row['paid_at']) : null,
            $lineas,
            $row['codigo'] !== null ? (string) $row['codigo'] : null,
            $row['secuencia'] !== null ? (int) $row['secuencia'] : null,
        );
    }
}
