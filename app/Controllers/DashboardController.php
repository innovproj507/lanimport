<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Reporte\Application\ObtenerResumenDashboard\ObtenerResumenDashboardHandler;
use Courier\Reporte\Application\ObtenerResumenDashboard\ObtenerResumenDashboardQuery;

final class DashboardController extends BaseController
{
    public function index(): RedirectResponse|string
    {
        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        // Bodega no tiene dashboard (el login la manda aqui): va directo a la app movil (/bodega).
        if ($sessionManager->currentRol() === 'bodega') {
            return redirect()->to('/bodega');
        }

        $resumen = $this->container()->get(ObtenerResumenDashboardHandler::class)
            ->handle(new ObtenerResumenDashboardQuery());

        return view('dashboard/index', [
            'rol' => $sessionManager->currentRol(),
            'resumen' => $resumen,
        ]);
    }
}
