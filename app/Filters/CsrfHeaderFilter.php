<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Csrf;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CSRF para llamadas JSON con sesion (app de bodega /bodega/api): el token va en la
 * cabecera X-CSRF-Token en vez del campo _csrf de los formularios.
 */
final class CsrfHeaderFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($request->getMethod() === 'GET' || Csrf::verify($request->getHeaderLine('X-CSRF-Token'))) {
            return null;
        }

        return service('response')->setStatusCode(419, 'Page Expired')->setJSON([
            'success' => false,
            'message' => 'Sesion expirada, recargue la pagina.',
            'errors' => [],
        ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
