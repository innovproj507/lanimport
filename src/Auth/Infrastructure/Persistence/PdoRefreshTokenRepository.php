<?php

declare(strict_types=1);

namespace Courier\Auth\Infrastructure\Persistence;

use PDO;
use Ramsey\Uuid\Uuid;

final class PdoRefreshTokenRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function store(string $usuarioId, string $jti, int $expiresAtTimestamp): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO jwt_refresh_tokens (id, usuario_id, token_hash, expires_at, revoked, created_at)
             VALUES (:id, :usuario_id, :token_hash, :expires_at, 0, NOW())'
        );

        $stmt->execute([
            ':id' => Uuid::uuid4()->toString(),
            ':usuario_id' => $usuarioId,
            ':token_hash' => hash('sha256', $jti),
            ':expires_at' => date('Y-m-d H:i:s', $expiresAtTimestamp),
        ]);
    }

    public function isValid(string $jti): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) FROM jwt_refresh_tokens
             WHERE token_hash = :hash AND revoked = 0 AND expires_at > NOW()'
        );
        $stmt->execute([':hash' => hash('sha256', $jti)]);

        return ((int) $stmt->fetchColumn()) > 0;
    }
}
