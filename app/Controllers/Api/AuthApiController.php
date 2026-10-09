<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use Courier\Auth\Application\Login\LoginCommand;
use Courier\Auth\Application\Login\LoginHandler;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Auth\Infrastructure\Persistence\PdoRefreshTokenRepository;
use Courier\Auth\Infrastructure\Security\JwtService;
use Throwable;

final class AuthApiController extends BaseApiController
{
    public function login(): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];

        try {
            $command = new LoginCommand(
                (string) ($body['email'] ?? ''),
                (string) ($body['password'] ?? ''),
                'api',
                $this->request->getIPAddress(),
            );

            $result = $this->container()->get(LoginHandler::class)->handle($command);

            return $this->success([
                'usuario_id' => $result->usuarioId,
                'nombre' => $result->nombre,
                'rol' => $result->rol,
                'access_token' => $result->accessToken,
                'refresh_token' => $result->refreshToken,
                'expires_in' => $result->expiresIn,
            ]);
        } catch (Throwable $e) {
            return $this->fromException($e);
        }
    }

    public function refresh(): ResponseInterface
    {
        $body = $this->request->getJSON(true) ?? [];
        $refreshToken = (string) ($body['refresh_token'] ?? '');

        /** @var JwtService $jwtService */
        $jwtService = $this->container()->get(JwtService::class);

        try {
            $claims = $jwtService->verify($refreshToken);
        } catch (Throwable) {
            return $this->error('Refresh token invalido o expirado.', 401);
        }

        /** @var PdoRefreshTokenRepository $refreshRepo */
        $refreshRepo = $this->container()->get(PdoRefreshTokenRepository::class);

        if (!isset($claims['jti']) || !$refreshRepo->isValid((string) $claims['jti'])) {
            return $this->error('Refresh token revocado o invalido.', 401);
        }

        /** @var UsuarioRepositoryInterface $usuarios */
        $usuarios = $this->container()->get(UsuarioRepositoryInterface::class);
        $usuario = $usuarios->findById((string) $claims['sub']);

        if ($usuario === null) {
            return $this->error('Usuario no encontrado.', 401);
        }

        return $this->success([
            'access_token' => $jwtService->issueAccessToken($usuario),
            'expires_in' => $jwtService->accessTtl(),
        ]);
    }
}
