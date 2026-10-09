<?php

declare(strict_types=1);

namespace Courier\Auth\Infrastructure\RateLimiting;

use PDO;
use Ramsey\Uuid\Uuid;

final class LoginAttemptRepository
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function isLockedOut(string $email, string $ipAddress): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) AS total FROM login_attempts
             WHERE email = :email AND ip_address = :ip AND exitoso = 0
               AND created_at >= (NOW() - INTERVAL :minutes MINUTE)'
        );
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':ip', $ipAddress);
        $stmt->bindValue(':minutes', self::WINDOW_MINUTES, PDO::PARAM_INT);
        $stmt->execute();

        $total = (int) $stmt->fetchColumn();

        return $total >= self::MAX_ATTEMPTS;
    }

    public function record(string $email, string $ipAddress, bool $exitoso): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO login_attempts (id, email, ip_address, exitoso, created_at)
             VALUES (:id, :email, :ip, :exitoso, NOW())'
        );
        $stmt->execute([
            ':id' => Uuid::uuid4()->toString(),
            ':email' => $email,
            ':ip' => $ipAddress,
            ':exitoso' => $exitoso ? 1 : 0,
        ]);
    }
}
