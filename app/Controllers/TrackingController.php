<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use Courier\Carga\Application\ConsultarTrackingPorNumero\ConsultarTrackingPorNumeroHandler;
use App\Libraries\AlcanceRastreo;
use Courier\Carga\Domain\Exception\CargaNotFoundException;
use Courier\Carga\Domain\ValueObject\TrackingNumero;
use Courier\Salida\Domain\RastreoCargaInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use Throwable;

final class TrackingController extends BaseController
{
    /** Roles internos: ven el detalle completo (cliente, bultos, OS/DO, contenedor, movimientos). */
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
                ->handle(AlcanceRastreo::query($this->container(), $numero, $_SESSION['usuario_rol'] ?? null, $_SESSION['usuario_id'] ?? null));

            return $this->render($view, null, $numero);
        } catch (ValidationException|CargaNotFoundException $e) {
            // Una carga borrada no desaparece: queda archivada y aqui se explica que se elimino
            // (solo al personal interno: del archivo no se sabe si era de este cliente).
            $eliminada = AlcanceRastreo::limitado($_SESSION['usuario_rol'] ?? null)
                ? null
                : $this->container()->get(RastreoCargaInterface::class)->eliminada($this->normalizar($numero));

            if ($eliminada !== null) {
                return $this->render(null, null, $numero, $eliminada);
            }

            return $this->render(null, $e->getMessage(), $numero);
        }
    }

    private function normalizar(string $numero): string
    {
        try {
            return (string) new TrackingNumero(TrackingNumero::normalizarBusqueda($numero));
        } catch (Throwable) {
            return strtoupper(trim($numero));
        }
    }

    /** @param array<string, mixed>|null $eliminada */
    private function render(?object $view, ?string $error, string $numero, ?array $eliminada = null): string
    {
        $interno = in_array($_SESSION['usuario_rol'] ?? null, self::ROLES_INTERNOS, true);
        $rastreo = $interno ? $this->container()->get(RastreoCargaInterface::class) : null;

        return view('tracking/lookup', [
            'view' => $view,
            'error' => $error,
            'numero' => $numero,
            'interno' => $interno,
            'rastreo' => $view !== null && $rastreo !== null ? $rastreo->detalle($view->cargaId) : null,
            'movimientos' => $view !== null && $rastreo !== null ? $rastreo->movimientos($view->cargaId) : null,
            'eliminada' => $eliminada,
        ]);
    }
}
