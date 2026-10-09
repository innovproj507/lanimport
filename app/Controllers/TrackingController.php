<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroHandler;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroQuery;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class TrackingController extends BaseController
{
    public function showLookupForm(): string
    {
        return view('tracking/lookup', ['view' => null, 'error' => null]);
    }

    public function lookup(): string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('tracking/lookup', ['view' => null, 'error' => 'Sesion expirada, por favor intente de nuevo.']);
        }

        $numero = (string) $this->request->getPost('tracking_numero');

        try {
            $view = $this->container()->get(ConsultarTrackingPorNumeroHandler::class)
                ->handle(new ConsultarTrackingPorNumeroQuery($numero));

            return view('tracking/lookup', ['view' => $view, 'error' => null]);
        } catch (ValidationException|CargaNotFoundException $e) {
            return view('tracking/lookup', ['view' => null, 'error' => $e->getMessage()]);
        }
    }
}
