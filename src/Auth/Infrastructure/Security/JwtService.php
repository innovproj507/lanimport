<?php

declare(strict_types=1);

namespace Courier\Auth\Infrastructure\Security;

use Courier\Auth\Domain\Usuario;
use Courier\Shared\Infrastructure\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class JwtService
{
    private string $secret;
    private int $accessTtl;
    private int $refreshTtl;

    public function __construct()
    {
        $this->secret = (string) Config::get('JWT_SECRET', 'changeme');
        $this->accessTtl = Config::int('JWT_ACCESS_TTL', 1800);
        $this->refreshTtl = Config::int('JWT_REFRESH_TTL', 1209600);
    }

    public function issueAccessToken(Usuario $usuario): string
    {
        $now = time();

        return JWT::encode([
            'sub' => $usuario->id(),
            'rol' => $usuario->rol()->value,
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
        ], $this->secret, 'HS256');
    }

    /** @return array{token: string, jti: string, expiresAt: int} */
    public function issueRefreshToken(Usuario $usuario): array
    {
        $now = time();
        $jti = RamseyUuid::uuid4()->toString();
        $expiresAt = $now + $this->refreshTtl;

        $token = JWT::encode([
            'sub' => $usuario->id(),
            'jti' => $jti,
            'iat' => $now,
            'exp' => $expiresAt,
        ], $this->secret, 'HS256');

        return ['token' => $token, 'jti' => $jti, 'expiresAt' => $expiresAt];
    }

    public function accessTtl(): int
    {
        return $this->accessTtl;
    }

    /** @return array<string, mixed> */
    public function verify(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

        return (array) $decoded;
    }
}
