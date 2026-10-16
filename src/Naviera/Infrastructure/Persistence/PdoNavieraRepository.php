<?php

declare(strict_types=1);

namespace Courier\Naviera\Infrastructure\Persistence;

use Courier\Naviera\Domain\Naviera;
use Courier\Naviera\Domain\NavieraRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoNavieraRepository implements NavieraRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Naviera $naviera): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO navieras (id, nombre, activo, created_at)
             VALUES (:id, :nombre, :activo, :created_at)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = VALUES(activo)'
        );

        $stmt->execute([
            ':id' => $naviera->id(),
            ':nombre' => $naviera->nombre(),
            ':activo' => $naviera->activo() ? 1 : 0,
            ':created_at' => $naviera->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Naviera
    {
        $stmt = $this->connection->prepare('SELECT * FROM navieras WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(bool $soloActivas = false): array
    {
        $stmt = $this->connection->query(
            'SELECT * FROM navieras' . ($soloActivas ? ' WHERE activo = 1' : '') . ' ORDER BY nombre ASC'
        );

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
            $where = 'nombre LIKE :term';
            $params = [':term' => '%' . $term . '%'];
        }

        $countStmt = $this->connection->prepare("SELECT COUNT(*) FROM navieras WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->connection->prepare("SELECT * FROM navieras WHERE {$where} ORDER BY activo DESC, nombre ASC LIMIT :limit OFFSET :offset");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll()), 'total' => $total];
    }

    public function existsNombre(string $nombre, ?string $excluirId = null): bool
    {
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM navieras WHERE nombre = :nombre AND id <> :excluir');
        $stmt->execute([':nombre' => $nombre, ':excluir' => $excluirId ?? '']);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function referencias(string $id): array
    {
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM salida_ordenes WHERE naviera_id = :id');
        $stmt->execute([':id' => $id]);

        return array_filter(['salidas' => (int) $stmt->fetchColumn()]);
    }

    public function delete(string $id): void
    {
        $this->connection->prepare('DELETE FROM navieras WHERE id = :id')->execute([':id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Naviera
    {
        return new Naviera(
            new Uuid((string) $row['id']),
            (string) $row['nombre'],
            (bool) $row['activo'],
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
