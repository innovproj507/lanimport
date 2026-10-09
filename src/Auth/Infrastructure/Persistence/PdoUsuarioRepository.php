<?php

declare(strict_types=1);

namespace Courier\Auth\Infrastructure\Persistence;

use Courier\Auth\Domain\Rol;
use Courier\Auth\Domain\Usuario;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Shared\Domain\ValueObject\EmailAddress;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoUsuarioRepository implements UsuarioRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findByEmail(string $email): ?Usuario
    {
        $stmt = $this->connection->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findById(string $id): ?Usuario
    {
        $stmt = $this->connection->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(Usuario $usuario): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO usuarios (id, nombre, email, password_hash, rol, activo, created_at, updated_at)
             VALUES (:id, :nombre, :email, :password_hash, :rol, :activo, :created_at, NOW())
             ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre), email = VALUES(email), password_hash = VALUES(password_hash),
                rol = VALUES(rol), activo = VALUES(activo), updated_at = NOW()'
        );

        $stmt->execute([
            ':id' => $usuario->id(),
            ':nombre' => $usuario->nombre(),
            ':email' => (string) $usuario->email(),
            ':password_hash' => $usuario->passwordHash(),
            ':rol' => $usuario->rol()->value,
            ':activo' => $usuario->activo() ? 1 : 0,
            ':created_at' => $usuario->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findAll(): array
    {
        $stmt = $this->connection->query('SELECT * FROM usuarios ORDER BY nombre ASC');

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
            $where = '(nombre LIKE :term1 OR email LIKE :term2)';
            $likeTerm = '%' . $term . '%';
            $params = [
                ':term1' => $likeTerm,
                ':term2' => $likeTerm,
            ];
        }

        $countStmt = $this->connection->prepare("SELECT COUNT(*) FROM usuarios WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->connection->prepare("SELECT * FROM usuarios WHERE {$where} ORDER BY nombre ASC LIMIT :limit OFFSET :offset");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());

        return ['items' => $items, 'total' => $total];
    }

    public function existsEmail(string $email): bool
    {
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM usuarios WHERE email = :email');
        $stmt->execute([':email' => $email]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Usuario
    {
        return Usuario::reconstituir(
            new Uuid((string) $row['id']),
            (string) $row['nombre'],
            new EmailAddress((string) $row['email']),
            (string) $row['password_hash'],
            Rol::from((string) $row['rol']),
            (bool) $row['activo'],
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
