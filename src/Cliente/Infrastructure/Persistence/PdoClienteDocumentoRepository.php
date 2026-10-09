<?php

declare(strict_types=1);

namespace Courier\Cliente\Infrastructure\Persistence;

use Courier\Cliente\Domain\ClienteDocumento;
use Courier\Cliente\Domain\ClienteDocumentoRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoClienteDocumentoRepository implements ClienteDocumentoRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(ClienteDocumento $documento): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO cliente_documentos (id, cliente_id, nombre_archivo, ruta_archivo, tipo, created_at)
             VALUES (:id, :cliente_id, :nombre_archivo, :ruta_archivo, :tipo, :created_at)'
        );

        $stmt->execute([
            ':id' => $documento->id(),
            ':cliente_id' => $documento->clienteId(),
            ':nombre_archivo' => $documento->nombreArchivo(),
            ':ruta_archivo' => $documento->rutaArchivo(),
            ':tipo' => $documento->tipo(),
            ':created_at' => $documento->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByClienteId(string $clienteId): array
    {
        $stmt = $this->connection->prepare('SELECT * FROM cliente_documentos WHERE cliente_id = :cliente_id ORDER BY created_at ASC');
        $stmt->execute([':cliente_id' => $clienteId]);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ClienteDocumento
    {
        return new ClienteDocumento(
            new Uuid((string) $row['id']),
            (string) $row['cliente_id'],
            (string) $row['nombre_archivo'],
            (string) $row['ruta_archivo'],
            $row['tipo'] !== null ? (string) $row['tipo'] : null,
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
