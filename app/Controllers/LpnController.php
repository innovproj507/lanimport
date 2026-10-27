<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Lpn\Application\ActualizarEtiquetaLpnConfig\ActualizarEtiquetaLpnConfigCommand;
use Courier\Lpn\Application\ActualizarEtiquetaLpnConfig\ActualizarEtiquetaLpnConfigHandler;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnCommand;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnHandler;
use Courier\Lpn\Application\GenerarImagenesEtiquetasLpn\GenerarImagenesEtiquetasLpnCommand;
use Courier\Lpn\Application\GenerarImagenesEtiquetasLpn\GenerarImagenesEtiquetasLpnHandler;
use Courier\Lpn\Application\ListarLpnsPorCarga\ListarLpnsPorCargaHandler;
use Courier\Lpn\Application\ListarLpnsPorCarga\ListarLpnsPorCargaQuery;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesHandler;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesQuery;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class LpnController extends BaseController
{
    public function showRecepcion(string $id): string
    {
        $carga = $this->container()->get(CargaRepositoryInterface::class)->findById($id);

        if ($carga === null) {
            $this->response->setStatusCode(404);

            return 'Carga no encontrada.';
        }

        $lpns = $this->container()->get(ListarLpnsPorCargaHandler::class)->handle(new ListarLpnsPorCargaQuery($id));

        if ($this->request->getGet('print') !== null) {
            return view('lpn/etiquetas_imprimir', [
                'carga' => $carga,
                'lpns' => $lpns,
            ]);
        }

        $ubicaciones = $this->container()->get(ListarUbicacionesHandler::class)->handle(new ListarUbicacionesQuery());
        $imagenes = $this->container()->get(GenerarImagenesEtiquetasLpnHandler::class)
            ->handle(new GenerarImagenesEtiquetasLpnCommand($id, 0));

        return view('lpn/recepcion', [
            'carga' => $carga,
            'lpns' => $lpns,
            'ubicaciones' => $ubicaciones,
            'imagenesFaltan' => $imagenes->faltan,
        ]);
    }

    /** Una tanda de imagenes de etiquetas pendientes (cargas grandes): la pantalla repite hasta faltan = 0. */
    public function generarImagenes(string $id): ResponseInterface
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->response->setStatusCode(419, 'Page Expired')->setJSON([
                'success' => false,
                'message' => 'Sesion expirada, por favor recargue la pagina.',
            ]);
        }

        // La tanda tarda ~15 s: se suelta la sesion para no bloquear las demas pantallas del usuario.
        session_write_close();

        try {
            $resultado = $this->container()->get(GenerarImagenesEtiquetasLpnHandler::class)
                ->handle(new GenerarImagenesEtiquetasLpnCommand($id, 15));
        } catch (NotFoundException $e) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }

        return $this->response->setJSON([
            'success' => true,
            'total' => $resultado->total,
            'generadas' => $resultado->generadas,
            'faltan' => $resultado->faltan,
        ]);
    }

    /** Pantalla "Recepcion" de bodega: escanear cualquier LPN y ver las cargas que faltan por recibir. */
    public function recepcionGeneral(): string
    {
        $ubicaciones = $this->container()->get(ListarUbicacionesHandler::class)->handle(new ListarUbicacionesQuery());

        return view('lpn/recepcion_general', [
            'pendientes' => $this->container()->get(LpnRepositoryInterface::class)->resumenRecepcion(),
            'ubicaciones' => $ubicaciones,
        ]);
    }

    /** Escaneo general: el LPN puede ser de cualquier carga. */
    public function escanearRecepcion(): ResponseInterface
    {
        return $this->confirmar(null);
    }

    /** Escaneo desde la pantalla de una carga: el LPN debe ser de esa carga. */
    public function confirmarRecepcion(string $id): ResponseInterface
    {
        return $this->confirmar($id);
    }

    private function confirmar(?string $cargaId): ResponseInterface
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->response->setStatusCode(419, 'Page Expired')->setJSON([
                'success' => false,
                'message' => 'Sesion expirada, por favor recargue la pagina.',
            ]);
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        try {
            $ubicacionId = (string) $this->request->getPost('ubicacion_id');

            $command = new ConfirmarRecepcionLpnCommand(
                (string) $this->request->getPost('codigo'),
                $ubicacionId !== '' ? $ubicacionId : null,
                (string) $sessionManager->currentUsuarioId(),
                $cargaId,
            );

            $lpn = $this->container()->get(ConfirmarRecepcionLpnHandler::class)->handle($command);
            $progreso = $lpn->cargaId() !== null
                ? ($this->container()->get(LpnRepositoryInterface::class)->resumenRecepcion($lpn->cargaId())[0] ?? null)
                : null;

            return $this->response->setJSON([
                'success' => true,
                'lpn' => ['codigo' => $lpn->codigo(), 'estado' => $lpn->estado()->value, 'estadoLabel' => $lpn->estado()->label()],
                'progreso' => $progreso,
            ]);
        } catch (NotFoundException|ValidationException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function guardarPlantillaEtiqueta(): RedirectResponse
    {
        $volver = (string) ($this->request->getPost('volver') ?? '/cargas/ingreso');

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to($volver);
        }

        $command = new ActualizarEtiquetaLpnConfigCommand(
            $this->request->getPost('mostrar_marca') !== null,
            $this->request->getPost('mostrar_proveedor') !== null,
            $this->request->getPost('mostrar_serie') !== null,
            $this->request->getPost('mostrar_descripcion') !== null,
        );

        $this->container()->get(ActualizarEtiquetaLpnConfigHandler::class)->handle($command);

        return redirect()->to($volver);
    }
}
