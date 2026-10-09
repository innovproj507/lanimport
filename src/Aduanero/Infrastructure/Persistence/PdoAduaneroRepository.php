<?php

declare(strict_types=1);

namespace Courier\Aduanero\Infrastructure\Persistence;

use Courier\Aduanero\Domain\Aduanero;
use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoAduaneroRepository implements AduaneroRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Aduanero $aduanero): void
    {
        if ($this->findById($aduanero->id()) === null) {
            $this->insert($aduanero);

            return;
        }

        $this->update($aduanero);
    }

    private function insert(Aduanero $aduanero): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO aduaneros (id, secuencia, identificador, nombre, pais, usuario_id, activo, created_at)
             VALUES (:id, NULL, :identificador, :nombre, :pais, :usuario_id, :activo, :created_at)'
        );

        $stmt->execute([
            ':id' => $aduanero->id(),
            ':identificador' => $aduanero->id(),
            ':nombre' => $aduanero->nombre(),
            ':pais' => $aduanero->pais(),
            ':usuario_id' => $aduanero->usuarioId(),
            ':activo' => $aduanero->activo() ? 1 : 0,
            ':created_at' => $aduanero->createdAt()->format('Y-m-d H:i:s'),
        ]);

        $secuencia = (int) $this->connection->lastInsertId();
        $identificador = (string) $secuencia;

        $update = $this->connection->prepare('UPDATE aduaneros SET identificador = :identificador WHERE id = :id');
        $update->execute([':identificador' => $identificador, ':id' => $aduanero->id()]);
    }

    private function update(Aduanero $aduanero): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE aduaneros SET nombre = :nombre, pais = :pais, activo = :activo, usuario_id = :usuario_id WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $aduanero->id(),
            ':nombre' => $aduanero->nombre(),
            ':pais' => $aduanero->pais(),
            ':activo' => $aduanero->activo() ? 1 : 0,
            ':usuario_id' => $aduanero->usuarioId(),
        ]);
    }

    public function findById(string $id): ?Aduanero
    {
        $stmt = $this->connection->prepare('SELECT * FROM aduaneros WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByIdentificador(string $identificador): ?Aduanero
    {
        $stmt = $this->connection->prepare('SELECT * FROM aduaneros WHERE identificador = :identificador LIMIT 1');
        $stmt->execute([':identificador' => $identificador]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAllActivos(): array
    {
        $stmt = $this->connection->query('SELECT * FROM aduaneros WHERE activo = 1 ORDER BY nombre ASC');

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function searchPaginado(string $term, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $term = trim($term);

        $where = 'activo = 1';
        $params = [];

        if ($term !== '') {
            $where .= ' AND (identificador LIKE :term1 OR nombre LIKE :term2)';
            $likeTerm = '%' . $term . '%';
            $params = [
                ':term1' => $likeTerm,
                ':term2' => $likeTerm,
            ];
        }

        $countStmt = $this->connection->prepare("SELECT COUNT(*) FROM aduaneros WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->connection->prepare("SELECT * FROM aduaneros WHERE {$where} ORDER BY secuencia ASC LIMIT :limit OFFSET :offset");

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
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM aduaneros WHERE identificador = :identificador');
        $stmt->execute([':identificador' => $identificador]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Aduanero
    {
        return new Aduanero(
            new Uuid((string) $row['id']),
            (string) $row['identificador'],
            (string) $row['nombre'],
            $row['pais'] !== null ? (string) $row['pais'] : null,
            $row['usuario_id'] !== null ? (string) $row['usuario_id'] : null,
            (bool) $row['activo'],
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
