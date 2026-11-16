<?php

declare(strict_types=1);

namespace Courier\Lpn\Infrastructure\Persistence;

use Courier\Lpn\Domain\Ubicacion;
use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Shared\Domain\BodegaActivaInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoUbicacionRepository implements UbicacionRepositoryInterface
{
    public function __construct(
        private readonly PDO $connection,
        private readonly ?BodegaActivaInterface $bodegaActiva = null,
    ) {
    }

    /**
     * Filtro por la bodega activa (vacio = todas).
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function filtroBodega(): array
    {
        $id = $this->bodegaActiva?->id();

        return $id !== null ? [' AND bodega_id = :bodega_activa', [':bodega_activa' => $id]] : ['', []];
    }

    public function save(Ubicacion $ubicacion): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO ubicaciones (id, bodega_id, codigo, zona, pasillo, nivel, activo, created_at)
             VALUES (:id, :bodega_id, :codigo, :zona, :pasillo, :nivel, :activo, :created_at)
             ON DUPLICATE KEY UPDATE zona = VALUES(zona), pasillo = VALUES(pasillo), nivel = VALUES(nivel), activo = VALUES(activo)'
        );

        $stmt->execute([
            ':id' => $ubicacion->id(),
            ':bodega_id' => $ubicacion->bodegaId(),
            ':codigo' => $ubicacion->codigo(),
            ':zona' => $ubicacion->zona(),
            ':pasillo' => $ubicacion->pasillo(),
            ':nivel' => $ubicacion->nivel(),
            ':activo' => $ubicacion->activo() ? 1 : 0,
            ':created_at' => $ubicacion->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findAll(): array
    {
        [$filtro, $params] = $this->filtroBodega();
        $stmt = $this->connection->prepare("SELECT * FROM ubicaciones WHERE activo = 1{$filtro} ORDER BY codigo ASC");
        $stmt->execute($params);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function searchPaginado(string $term, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $term = trim($term);

        [$filtro, $params] = $this->filtroBodega();
        $where = '1=1' . $filtro;

        if ($term !== '') {
            $where .= ' AND (codigo LIKE :term1 OR zona LIKE :term2 OR pasillo LIKE :term3 OR nivel LIKE :term4)';
            $likeTerm = '%' . $term . '%';
            $params += [
                ':term1' => $likeTerm,
                ':term2' => $likeTerm,
                ':term3' => $likeTerm,
                ':term4' => $likeTerm,
            ];
        }

        $countStmt = $this->connection->prepare("SELECT COUNT(*) FROM ubicaciones WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->connection->prepare("SELECT * FROM ubicaciones WHERE {$where} ORDER BY codigo ASC LIMIT :limit OFFSET :offset");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());

        return ['items' => $items, 'total' => $total];
    }

    public function existsCodigo(string $codigo, ?string $bodegaId = null): bool
    {
        $bodegaId ??= $this->bodegaActiva?->id();
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM ubicaciones WHERE codigo = :codigo' . ($bodegaId !== null ? ' AND bodega_id = :bodega' : ''));
        $stmt->execute([':codigo' => $codigo] + ($bodegaId !== null ? [':bodega' => $bodegaId] : []));

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function findById(string $id): ?Ubicacion
    {
        $stmt = $this->connection->prepare('SELECT * FROM ubicaciones WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCodigo(string $codigo): ?Ubicacion
    {
        [$filtro, $params] = $this->filtroBodega();
        $stmt = $this->connection->prepare("SELECT * FROM ubicaciones WHERE codigo = :codigo{$filtro} LIMIT 1");
        $stmt->execute([':codigo' => $codigo] + $params);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Ubicacion
    {
        $ubicacion = new Ubicacion(
            new Uuid((string) $row['id']),
            (string) $row['codigo'],
            $row['zona'] !== null ? (string) $row['zona'] : null,
            $row['pasillo'] !== null ? (string) $row['pasillo'] : null,
            $row['nivel'] !== null ? (string) $row['nivel'] : null,
            (bool) $row['activo'],
            new DateTimeImmutable((string) $row['created_at']),
        );
        $ubicacion->asignarBodega((string) ($row['bodega_id'] ?? '00000000-0000-0000-0000-000000000001'));

        return $ubicacion;
    }
}
