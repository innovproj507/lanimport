<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Courier\Auth\Infrastructure\Security\SessionManager;

/**
 * Equivalente a SessionAuthMiddleware (interfaces/Web/Middleware) del
 * sistema original: exige sesion activa, redirige a /login si no la hay.
 */
final class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $sessionManager = Services::courierContainer()->get(SessionManager::class);

        if ($sessionManager->currentUsuarioId() === null) {
            return redirect()->to('/login');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
