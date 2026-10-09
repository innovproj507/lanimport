<?php

declare(strict_types=1);

namespace Courier\Auth\Application\Login;

use Courier\Auth\Domain\Exception\InvalidCredentialsException;
use Courier\Auth\Domain\Exception\TooManyAttemptsException;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Auth\Infrastructure\Persistence\PdoRefreshTokenRepository;
use Courier\Auth\Infrastructure\RateLimiting\LoginAttemptRepository;
use Courier\Auth\Infrastructure\Security\JwtService;
use Courier\Auth\Infrastructure\Security\PasswordHasher;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;

final class LoginHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly PasswordHasher $hasher,
        private readonly JwtService $jwtService,
        private readonly SessionManager $sessionManager,
        private readonly LoginAttemptRepository $attempts,
        private readonly PdoRefreshTokenRepository $refreshTokens,
    ) {
    }

    public function handle(CommandInterface $command): LoginResult
    {
        assert($command instanceof LoginCommand);

        if ($this->attempts->isLockedOut($command->email, $command->ipAddress)) {
            throw new TooManyAttemptsException('Demasiados intentos fallidos. Intente de nuevo mas tarde.');
        }

        $usuario = $this->usuarios->findByEmail($command->email);

        if ($usuario === null || !$usuario->activo() || !$this->hasher->verify($command->password, $usuario->passwordHash())) {
            $this->attempts->record($command->email, $command->ipAddress, false);

            throw new InvalidCredentialsException('Credenciales invalidas.');
        }

        $this->attempts->record($command->email, $command->ipAddress, true);

        if ($command->clientType === 'api') {
            $accessToken = $this->jwtService->issueAccessToken($usuario);
            $refresh = $this->jwtService->issueRefreshToken($usuario);
            $this->refreshTokens->store($usuario->id(), $refresh['jti'], $refresh['expiresAt']);

            return new LoginResult(
                $usuario->id(),
                $usuario->nombre(),
                $usuario->rol()->value,
                $accessToken,
                $refresh['token'],
                $this->jwtService->accessTtl(),
            );
        }

        $this->sessionManager->start($usuario, $command->recordar);

        return new LoginResult($usuario->id(), $usuario->nombre(), $usuario->rol()->value);
    }
}
