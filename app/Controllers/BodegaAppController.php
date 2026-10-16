<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * App movil de bodega (PDA/tablet) en /bodega: una sola pagina que pide los datos a
 * /bodega/api/* (Api\BodegaApiController) con la misma sesion del sistema.
 */
final class BodegaAppController extends BaseController
{
    public function index(): string
    {
        return view('bodega/app', ['nombre' => (string) ($_SESSION['usuario_nombre'] ?? '')]);
    }

    /** El service worker se sirve desde /bodega para que su alcance cubra la app. */
    public function serviceWorker(): ResponseInterface
    {
        return $this->response
            ->setContentType('application/javascript')
            ->setHeader('Cache-Control', 'no-cache')
            // El script esta en /bodega/sw.js; este permiso le deja controlar tambien la pagina /bodega.
            ->setHeader('Service-Worker-Allowed', '/bodega')
            ->setBody((string) file_get_contents(FCPATH . 'assets/bodega/sw.js'));
    }
}
