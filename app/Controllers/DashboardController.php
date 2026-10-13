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

        // Roles operativos sin dashboard (el login los manda aqui): recepcion -> actas, bodega -> ingreso.
        $inicioPorRol = ['recepcion' => '/recepciones', 'bodega' => '/cargas/ingreso'];

        if (isset($inicioPorRol[$sessionManager->currentRol()])) {
            return redirect()->to($inicioPorRol[$sessionManager->currentRol()]);
        }

        $resumen = $this->container()->get(ObtenerResumenDashboardHandler::class)
            ->handle(new ObtenerResumenDashboardQuery());

        return view('dashboard/index', [
            'rol' => $sessionManager->currentRol(),
            'resumen' => $resumen,
        ]);
    }
}
