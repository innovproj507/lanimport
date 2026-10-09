<?php

declare(strict_types=1);

namespace Courier\Cliente\Infrastructure\Persistence;

use Courier\Cliente\Domain\Cliente;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Cliente\Domain\EstadoCliente;
use Courier\Shared\Domain\ValueObject\EmailAddress;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoClienteRepository implements ClienteRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Cliente $cliente): void
    {
        if ($this->findById($cliente->id()) === null) {
            $this->insert($cliente);

            return;
        }

        $this->update($cliente);
    }

    private function insert(Cliente $cliente): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO clientes
                (id, usuario_id, nombre, apellido, email, telefono, direccion, empresa, pais, estado, activo, created_at)
             VALUES
                (:id, :usuario_id, :nombre, :apellido, :email, :telefono, :direccion, :empresa, :pais, :estado, :activo, :created_at)'
        );

        $stmt->execute([
            ':id' => $cliente->id(),
            ':usuario_id' => $cliente->usuarioId(),
            ':nombre' => $cliente->nombre(),
            ':apellido' => $cliente->apellido(),
            ':email' => (string) $cliente->email(),
            ':telefono' => $cliente->telefono(),
            ':direccion' => $cliente->direccion(),
            ':empresa' => $cliente->empresa(),
            ':pais' => $cliente->pais(),
            ':estado' => $cliente->estado()->value,
            ':activo' => $cliente->activo() ? 1 : 0,
            ':created_at' => $cliente->createdAt()->format('Y-m-d H:i:s'),
        ]);

        $secuencia = (int) $this->connection->lastInsertId();
        $codigo = (string) $secuencia;

        $update = $this->connection->prepare('UPDATE clientes SET codigo = :codigo WHERE id = :id');
        $update->execute([':codigo' => $codigo, ':id' => $cliente->id()]);
    }

    private function update(Cliente $cliente): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE clientes SET
                usuario_id = :usuario_id, nombre = :nombre, apellido = :apellido, email = :email,
                telefono = :telefono, direccion = :direccion, empresa = :empresa, pais = :pais,
                estado = :estado, activo = :activo
             WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $cliente->id(),
            ':usuario_id' => $cliente->usuarioId(),
            ':nombre' => $cliente->nombre(),
            ':apellido' => $cliente->apellido(),
            ':email' => (string) $cliente->email(),
            ':telefono' => $cliente->telefono(),
            ':direccion' => $cliente->direccion(),
            ':empresa' => $cliente->empresa(),
            ':pais' => $cliente->pais(),
            ':estado' => $cliente->estado()->value,
            ':activo' => $cliente->activo() ? 1 : 0,
        ]);
    }

    public function findById(string $id): ?Cliente
    {
        $stmt = $this->connection->prepare('SELECT * FROM clientes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUsuarioId(string $usuarioId): ?Cliente
    {
        $stmt = $this->connection->prepare('SELECT * FROM clientes WHERE usuario_id = :usuario_id LIMIT 1');
        $stmt->execute([':usuario_id' => $usuarioId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->connection->query('SELECT * FROM clientes ORDER BY nombre ASC');

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findApproved(): array
    {
        $stmt = $this->connection->query(
            "SELECT * FROM clientes WHERE estado = 'aprobado' AND activo = 1 ORDER BY nombre ASC"
        );

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findPending(): array
    {
        $stmt = $this->connection->query(
            "SELECT * FROM clientes WHERE estado = 'pendiente' ORDER BY created_at ASC"
        );

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function search(string $term): array
    {
        if (trim($term) === '') {
            return $this->findApproved();
        }

        $stmt = $this->connection->prepare(
            "SELECT * FROM clientes
             WHERE estado = 'aprobado' AND activo = 1
               AND (nombre LIKE :term1 OR apellido LIKE :term2 OR email LIKE :term3 OR empresa LIKE :term4 OR codigo LIKE :term5)
             ORDER BY nombre ASC"
        );
        $likeTerm = '%' . $term . '%';
        $stmt->execute([
            ':term1' => $likeTerm,
            ':term2' => $likeTerm,
            ':term3' => $likeTerm,
            ':term4' => $likeTerm,
            ':term5' => $likeTerm,
        ]);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function searchPaginado(string $term, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $term = trim($term);

        $where = "estado = 'aprobado' AND activo = 1";
        $params = [];

        if ($term !== '') {
            $where .= ' AND (nombre LIKE :term1 OR apellido LIKE :term2 OR email LIKE :term3 OR empresa LIKE :term4 OR codigo LIKE :term5)';
            $likeTerm = '%' . $term . '%';
            $params = [
                ':term1' => $likeTerm,
                ':term2' => $likeTerm,
                ':term3' => $likeTerm,
                ':term4' => $likeTerm,
                ':term5' => $likeTerm,
            ];
        }

        $countStmt = $this->connection->prepare("SELECT COUNT(*) FROM clientes WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->connection->prepare("SELECT * FROM clientes WHERE {$where} ORDER BY secuencia ASC LIMIT :limit OFFSET :offset");

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
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM clientes WHERE email = :email');
        $stmt->execute([':email' => $email]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function existsEmailExcluding(string $email, string $excludeId): bool
    {
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM clientes WHERE email = :email AND id != :exclude_id');
        $stmt->execute([':email' => $email, ':exclude_id' => $excludeId]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Cliente
    {
        return new Cliente(
            new Uuid((string) $row['id']),
            $row['usuario_id'] !== null ? (string) $row['usuario_id'] : null,
            (string) $row['nombre'],
            $row['apellido'] !== null ? (string) $row['apellido'] : null,
            new EmailAddress((string) $row['email']),
            $row['telefono'] !== null ? (string) $row['telefono'] : null,
            $row['direccion'] !== null ? (string) $row['direccion'] : null,
            $row['empresa'] !== null ? (string) $row['empresa'] : null,
            $row['pais'] !== null ? (string) $row['pais'] : null,
            EstadoCliente::from((string) $row['estado']),
            (bool) $row['activo'],
            new DateTimeImmutable((string) $row['created_at']),
            $row['codigo'] !== null ? (string) $row['codigo'] : null,
            $row['secuencia'] !== null ? (int) $row['secuencia'] : null,
        );
    }
}
