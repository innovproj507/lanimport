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
use Courier\Lpn\Application\ListarLpnsPorCarga\ListarLpnsPorCargaHandler;
use Courier\Lpn\Application\ListarLpnsPorCarga\ListarLpnsPorCargaQuery;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesHandler;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesQuery;
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

        return view('lpn/recepcion', [
            'carga' => $carga,
            'lpns' => $lpns,
            'ubicaciones' => $ubicaciones,
        ]);
    }

    public function confirmarRecepcion(string $id): ResponseInterface
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->response->setStatusCode(419)->setJSON([
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
            );

            $lpn = $this->container()->get(ConfirmarRecepcionLpnHandler::class)->handle($command);

            return $this->response->setJSON([
                'success' => true,
                'lpn' => ['codigo' => $lpn->codigo(), 'estado' => $lpn->estado()->value, 'estadoLabel' => $lpn->estado()->label()],
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
