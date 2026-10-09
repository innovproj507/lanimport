<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Courier\Auth\Infrastructure\Security\JwtService;
use Firebase\JWT\ExpiredException;
use Throwable;
use UnexpectedValueException;

/**
 * Equivalente a JwtAuthMiddleware (interfaces/Api/Middleware) del sistema
 * original: valida el Bearer token y deja los claims (usuario_id, rol)
 * disponibles para el controller via ApiAuthContext.
 */
final class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if ($header === '' || !str_starts_with($header, 'Bearer ')) {
            return service('response')->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Token de autenticacion no proporcionado.',
                'errors' => [],
            ]);
        }

        $token = substr($header, 7);

        /** @var JwtService $jwtService */
        $jwtService = Services::courierContainer()->get(JwtService::class);

        try {
            $claims = $jwtService->verify($token);
        } catch (ExpiredException) {
            return service('response')->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'El token ha expirado.',
                'errors' => [],
            ]);
        } catch (UnexpectedValueException|Throwable) {
            return service('response')->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Token invalido.',
                'errors' => [],
            ]);
        }

        $context = Services::apiAuthContext();
        $context->usuarioId = $claims['sub'] ?? null;
        $context->rol = $claims['rol'] ?? null;

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
