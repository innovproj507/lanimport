<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroHandler;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroQuery;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Salida\Domain\RastreoCargaInterface;
use Courier\Shared\Domain\Exception\ValidationException;

final class TrackingController extends BaseController
{
    /** Roles internos: ven el detalle completo (cliente, bultos, OS/DO, contenedor). */
    private const ROLES_INTERNOS = ['inventario', 'operaciones', 'gerente', 'admin'];

    /** GET /tracking (con ?numero=... busca directo, para enlazar desde otras pantallas). */
    public function showLookupForm(): string
    {
        $numero = trim((string) $this->request->getGet('numero'));

        return $numero !== '' ? $this->buscar($numero) : $this->render(null, null, '');
    }

    public function lookup(): string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->render(null, 'Sesion expirada, por favor intente de nuevo.', '');
        }

        return $this->buscar(trim((string) $this->request->getPost('tracking_numero')));
    }

    private function buscar(string $numero): string
    {
        try {
            $view = $this->container()->get(ConsultarTrackingPorNumeroHandler::class)
                ->handle(new ConsultarTrackingPorNumeroQuery($numero));

            return $this->render($view, null, $numero);
        } catch (ValidationException|CargaNotFoundException $e) {
            return $this->render(null, $e->getMessage(), $numero);
        }
    }

    private function render(?object $view, ?string $error, string $numero): string
    {
        $interno = in_array($_SESSION['usuario_rol'] ?? null, self::ROLES_INTERNOS, true);

        return view('tracking/lookup', [
            'view' => $view,
            'error' => $error,
            'numero' => $numero,
            'interno' => $interno,
            'rastreo' => $view !== null && $interno
                ? $this->container()->get(RastreoCargaInterface::class)->detalle($view->cargaId)
                : null,
        ]);
    }
}
