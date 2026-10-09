<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Courier\Auth\Infrastructure\Security\SessionManager;

/**
 * Equivalente a RoleMiddleware (interfaces/Web/Middleware) del sistema
 * original: exige que el rol de la sesion actual este en la lista de
 * roles permitidos, pasada como argumento del filtro (ej. `role:gerente,admin`).
 */
final class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $rolesPermitidos = $arguments ?? [];
        $rolActual = Services::courierContainer()->get(SessionManager::class)->currentRol();

        if (!in_array($rolActual, $rolesPermitidos, true)) {
            $response = service('response');
            $response->setStatusCode(403);
            $response->setBody('No tiene permisos para acceder a esta seccion.');

            return $response;
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
