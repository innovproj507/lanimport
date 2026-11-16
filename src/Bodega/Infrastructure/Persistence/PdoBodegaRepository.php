<?php

declare(strict_types=1);

namespace Courier\Bodega\Infrastructure\Persistence;

use Courier\Bodega\Domain\Bodega;
use Courier\Bodega\Domain\BodegaRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class PdoBodegaRepository implements BodegaRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Bodega $bodega): void
    {
        $this->connection->prepare(
            'INSERT INTO bodegas (id, codigo, nombre, direccion, activo, created_at)
             VALUES (:id, :codigo, :nombre, :direccion, :activo, :created_at)
             ON DUPLICATE KEY UPDATE codigo = VALUES(codigo), nombre = VALUES(nombre), direccion = VALUES(direccion), activo = VALUES(activo)'
        )->execute([
            ':id' => $bodega->id(),
            ':codigo' => $bodega->codigo(),
            ':nombre' => $bodega->nombre(),
            ':direccion' => $bodega->direccion(),
            ':activo' => $bodega->activo() ? 1 : 0,
            ':created_at' => $bodega->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Bodega
    {
        $stmt = $this->connection->prepare('SELECT * FROM bodegas WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn (array $r) => $this->hydrate($r), $this->connection->query('SELECT * FROM bodegas ORDER BY activo DESC, nombre')->fetchAll());
    }

    public function findAllActivas(): array
    {
        return array_map(fn (array $r) => $this->hydrate($r), $this->connection->query('SELECT * FROM bodegas WHERE activo = 1 ORDER BY nombre')->fetchAll());
    }

    public function existsCodigo(string $codigo, ?string $exceptoId = null): bool
    {
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM bodegas WHERE codigo = :codigo AND id <> :id');
        $stmt->execute([':codigo' => strtoupper(trim($codigo)), ':id' => $exceptoId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function bodegaDeUsuario(string $usuarioId): ?string
    {
        $stmt = $this->connection->prepare('SELECT bodega_id FROM usuarios WHERE id = :id');
        $stmt->execute([':id' => $usuarioId]);
        $id = $stmt->fetchColumn();

        return $id !== false && $id !== null && $id !== '' ? (string) $id : null;
    }

    public function asignarAUsuario(string $usuarioId, ?string $bodegaId): void
    {
        $this->connection->prepare('UPDATE usuarios SET bodega_id = :bodega WHERE id = :id')
            ->execute([':bodega' => $bodegaId !== '' ? $bodegaId : null, ':id' => $usuarioId]);
    }

    public function bodegasDeUsuarios(): array
    {
        return $this->connection->query('SELECT id, bodega_id FROM usuarios WHERE bodega_id IS NOT NULL')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @param array<string, mixed> $r */
    private function hydrate(array $r): Bodega
    {
        return Bodega::reconstituir(
            (string) $r['id'],
            (string) $r['codigo'],
            (string) $r['nombre'],
            $r['direccion'] !== null ? (string) $r['direccion'] : null,
            (bool) $r['activo'],
            new DateTimeImmutable((string) $r['created_at']),
        );
    }
}
