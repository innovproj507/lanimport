<?php

declare(strict_types=1);

namespace App\Controllers;

use Courier\Reporte\Application\ObtenerReporteOperativo\ObtenerReporteOperativoHandler;
use Courier\Reporte\Application\ObtenerReporteOperativo\ObtenerReporteOperativoQuery;

final class ReporteController extends BaseController
{
    public function index(): string
    {
        $desde = (string) ($this->request->getGet('desde') ?? date('Y-m-01'));
        $hasta = (string) ($this->request->getGet('hasta') ?? date('Y-m-d'));

        $reporte = $this->container()->get(ObtenerReporteOperativoHandler::class)
            ->handle(new ObtenerReporteOperativoQuery($desde, $hasta));

        return view('reporte/index', [
            'reporte' => $reporte,
        ]);
    }
}
