<?php

declare(strict_types=1);

namespace Courier\Proveedor\Infrastructure\Persistence;

use Courier\Proveedor\Domain\Proveedor;
use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoProveedorRepository implements ProveedorRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Proveedor $proveedor): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO proveedores (id, identificador, nombre, created_at)
             VALUES (:id, :identificador, :nombre, :created_at)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)'
        );

        $stmt->execute([
            ':id' => $proveedor->id(),
            ':identificador' => $proveedor->identificador(),
            ':nombre' => $proveedor->nombre(),
            ':created_at' => $proveedor->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Proveedor
    {
        $stmt = $this->connection->prepare('SELECT * FROM proveedores WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByIdentificador(string $identificador): ?Proveedor
    {
        $stmt = $this->connection->prepare('SELECT * FROM proveedores WHERE identificador = :identificador LIMIT 1');
        $stmt->execute([':identificador' => $identificador]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->connection->query('SELECT * FROM proveedores ORDER BY nombre ASC');

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function searchPaginado(string $term, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $term = trim($term);

        $where = '1=1';
        $params = [];

        if ($term !== '') {
            $where = '(identificador LIKE :term1 OR nombre LIKE :term2)';
            $likeTerm = '%' . $term . '%';
            $params = [
                ':term1' => $likeTerm,
                ':term2' => $likeTerm,
            ];
        }

        $countStmt = $this->connection->prepare("SELECT COUNT(*) FROM proveedores WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->connection->prepare("SELECT * FROM proveedores WHERE {$where} ORDER BY nombre ASC LIMIT :limit OFFSET :offset");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());

        return ['items' => $items, 'total' => $total];
    }

    public function existsIdentificador(string $identificador): bool
    {
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM proveedores WHERE identificador = :identificador');
        $stmt->execute([':identificador' => $identificador]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Proveedor
    {
        return new Proveedor(
            new Uuid((string) $row['id']),
            (string) $row['identificador'],
            (string) $row['nombre'],
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
