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
        $cliente = trim((string) $this->request->getGet('cliente'));
        $aduanero = trim((string) $this->request->getGet('aduanero'));

        if ($this->esInterno() && ($cliente !== '' || $aduanero !== '')) {
            return $this->porClienteOAduanero($cliente !== '' ? $cliente : null, $cliente === '' ? $aduanero : null, $numero);
        }

        return $numero !== '' ? $this->buscar($numero) : $this->render(null, null, '');
    }

    private function esInterno(): bool
    {
        return in_array($_SESSION['usuario_rol'] ?? null, self::ROLES_INTERNOS, true);
    }

    /**
     * Busqueda por nombre (personal interno): si el texto no es un tracking, se buscan clientes y
     * aduaneros. Uno solo: se muestran sus cargas; varios: se listan para elegir. null = ninguno.
     */
    private function buscarPorNombre(string $texto): ?string
    {
        if (!$this->esInterno() || mb_strlen($texto) < 3) {
            return null;
        }

        $coincidencias = $this->container()->get(RastreoCargaInterface::class)->buscarClientesYAduaneros($texto);
        $n = count($coincidencias['clientes']) + count($coincidencias['aduaneros']);

        if ($n === 0) {
            return null;
        }

        if ($n === 1) {
            return $coincidencias['clientes'] !== []
                ? $this->porClienteOAduanero($coincidencias['clientes'][0]['id'], null, $texto)
                : $this->porClienteOAduanero(null, $coincidencias['aduaneros'][0]['id'], $texto);
        }

        return view('tracking/lookup', ['view' => null, 'error' => null, 'numero' => $texto, 'interno' => true, 'coincidencias' => $coincidencias]);
    }

    /** Todas las cargas (en operacion, o ?todas=1) de un cliente o aduanero, con su rastreo completo. */
    private function porClienteOAduanero(?string $clienteId, ?string $aduanero, string $texto): string
    {
        $porPagina = 15;
        $todas = $this->request->getGet('todas') === '1';
        $pagina = max(1, (int) $this->request->getGet('page'));
        $rastreo = $this->container()->get(RastreoCargaInterface::class);
        $r = $rastreo->cargasDe($clienteId, $aduanero, $todas, $pagina, $porPagina);

        if ($r['nombre'] === null) {
            return $this->render(null, $clienteId !== null ? 'Cliente no encontrado.' : 'Aduanero no encontrado.', $texto);
        }

        $handler = $this->container()->get(ConsultarTrackingPorNumeroHandler::class);
        $cargas = [];
        foreach ($r['cargaIds'] as $i => $cargaId) {
            try {
                $cargas[] = [
                    'view' => $handler->handle(AlcanceRastreo::query($this->container(), $r['trackings'][$i], $_SESSION['usuario_rol'] ?? null, $_SESSION['usuario_id'] ?? null)),
                    'rastreo' => $rastreo->detalle($cargaId),
                ];
            } catch (Throwable) {
                // Una carga que no se pueda leer no tumba el listado.
            }
        }

        return view('tracking/lookup', [
            'view' => null,
            'error' => null,
            'numero' => $texto,
            'interno' => true,
            'listado' => [
                'tipo' => $clienteId !== null ? 'cliente' : 'aduanero',
                'id' => $clienteId ?? $aduanero,
                'nombre' => $r['nombre'],
                'todas' => $todas,
                'total' => $r['total'],
                'bultos' => $r['bultos'],
                'pagina' => $pagina,
                'paginas' => max(1, (int) ceil($r['total'] / $porPagina)),
                'cargas' => $cargas,
            ],
        ]);
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
        // Caja o paleta de reempaque (LPN-R00002-1): no tiene tracking propio; se muestra que
        // contiene y en que orden va. Solo personal interno.
        if (in_array($_SESSION['usuario_rol'] ?? null, self::ROLES_INTERNOS, true)
            && preg_match('/^LPN[-\'?_]?R\d+/i', trim($numero))) {
            $caja = $this->container()->get(RastreoCargaInterface::class)->caja($numero);

            if ($caja !== null) {
                return view('tracking/lookup', ['view' => null, 'error' => null, 'numero' => $numero, 'interno' => true, 'caja' => $caja]);
            }
        }

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

            // No es un tracking: el personal interno puede buscar por cliente o aduanero.
            if (($porNombre = $this->buscarPorNombre($numero)) !== null) {
                return $porNombre;
            }

            $mensaje = $e instanceof ValidationException
                ? "No se reconoce \"{$numero}\". Escriba el tracking completo (LAN-2026-XXXXXX), sus 6 caracteres finales (XXXXXX), el codigo de una etiqueta (LPN-XXXXXX-1) o de una caja de reempaque (LPN-R00001-1)"
                    . ($this->esInterno() ? ', o el nombre de un cliente o aduanero.' : '.')
                : $e->getMessage();

            return $this->render(null, $mensaje, $numero);
        }
    }

    private function normalizar(string $numero): string
    {
        $sufijo = ConsultarTrackingPorNumeroHandler::sufijo($numero);

        if ($sufijo !== null) {
            return $sufijo;
        }

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
