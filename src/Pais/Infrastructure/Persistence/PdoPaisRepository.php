<?php

declare(strict_types=1);

namespace Courier\Pais\Infrastructure\Persistence;

use Courier\Pais\Domain\Pais;
use Courier\Pais\Domain\PaisRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoPaisRepository implements PaisRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Pais $pais): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO paises (id, identificador, nombre, mostrar_serie_etiqueta, created_at)
             VALUES (:id, :identificador, :nombre, :mostrar_serie_etiqueta, :created_at)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), mostrar_serie_etiqueta = VALUES(mostrar_serie_etiqueta)'
        );

        $stmt->execute([
            ':id' => $pais->id(),
            ':identificador' => $pais->identificador(),
            ':nombre' => $pais->nombre(),
            ':mostrar_serie_etiqueta' => $pais->mostrarSerieEtiqueta() ? 1 : 0,
            ':created_at' => $pais->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Pais
    {
        $stmt = $this->connection->prepare('SELECT * FROM paises WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByIdentificador(string $identificador): ?Pais
    {
        $stmt = $this->connection->prepare('SELECT * FROM paises WHERE identificador = :identificador LIMIT 1');
        $stmt->execute([':identificador' => $identificador]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByNombre(string $nombre): ?Pais
    {
        $stmt = $this->connection->prepare('SELECT * FROM paises WHERE nombre = :nombre LIMIT 1');
        $stmt->execute([':nombre' => $nombre]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->connection->query('SELECT * FROM paises ORDER BY nombre ASC');

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

        $countStmt = $this->connection->prepare("SELECT COUNT(*) FROM paises WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->connection->prepare("SELECT * FROM paises WHERE {$where} ORDER BY nombre ASC LIMIT :limit OFFSET :offset");

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
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM paises WHERE identificador = :identificador');
        $stmt->execute([':identificador' => $identificador]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Pais
    {
        return new Pais(
            new Uuid((string) $row['id']),
            (string) $row['identificador'],
            (string) $row['nombre'],
            (bool) $row['mostrar_serie_etiqueta'],
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
