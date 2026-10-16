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
            // Llamadas JSON (app de bodega): 401 para que la app mande al login, no un HTML de redireccion.
            if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                return service('response')->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'Sesion vencida.',
                    'errors' => [],
                ]);
            }

            return redirect()->to('/login');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
