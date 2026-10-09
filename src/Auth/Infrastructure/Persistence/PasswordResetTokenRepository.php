<?php

declare(strict_types=1);

namespace Courier\Auth\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use Ramsey\Uuid\Uuid;

final class PasswordResetTokenRepository
{
    private const TTL_MINUTES = 60;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function generar(string $usuarioId): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable())->modify('+' . self::TTL_MINUTES . ' minutes');

        $stmt = $this->connection->prepare(
            'INSERT INTO password_reset_tokens (id, usuario_id, token_hash, expires_at, created_at)
             VALUES (:id, :usuario_id, :token_hash, :expires_at, NOW())'
        );
        $stmt->execute([
            ':id' => Uuid::uuid4()->toString(),
            ':usuario_id' => $usuarioId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /** @return array{usuarioId: string}|null */
    public function validar(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);

        $stmt = $this->connection->prepare(
            'SELECT usuario_id FROM password_reset_tokens
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at >= NOW()
             LIMIT 1'
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ? ['usuarioId' => (string) $row['usuario_id']] : null;
    }

    public function marcarUsado(string $token): void
    {
        $tokenHash = hash('sha256', $token);

        $stmt = $this->connection->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = :token_hash'
        );
        $stmt->execute([':token_hash' => $tokenHash]);
    }
}
