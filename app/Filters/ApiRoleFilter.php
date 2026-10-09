<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Equivalente a RoleMiddleware (interfaces/Api/Middleware) del sistema
 * original: exige que el rol de los claims JWT (dejados por JwtAuthFilter)
 * este en la lista de roles permitidos, pasada como argumento del filtro.
 */
final class ApiRoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $rolesPermitidos = $arguments ?? [];
        $rolActual = Services::apiAuthContext()->rol;

        if (!in_array($rolActual, $rolesPermitidos, true)) {
            return service('response')->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'No tiene permisos para acceder a este recurso.',
                'errors' => [],
            ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
