<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use Courier\Bodega\Application\GuardarBodega\GuardarBodegaCommand;
use Courier\Bodega\Application\GuardarBodega\GuardarBodegaHandler;
use Courier\Bodega\Domain\BodegaRepositoryInterface;
use Courier\Shared\Domain\BodegaActivaInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Infrastructure\Security\BodegaActivaSesion;

/** Configuracion > Bodegas (Owner) y el selector de bodega activa del header. */
final class BodegaController extends BaseController
{
    public function index(): string
    {
        return $this->render(null, null);
    }

    public function editar(string $id): string
    {
        $bodega = $this->container()->get(BodegaRepositoryInterface::class)->findById($id);

        if ($bodega === null) {
            $this->response->setStatusCode(404);
        }

        return $this->render($bodega?->id(), null);
    }

    public function guardar(): RedirectResponse|string
    {
        $id = trim((string) $this->request->getPost('id')) ?: null;

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->render($id, 'Sesion expirada, por favor intente de nuevo.');
        }

        try {
            $this->container()->get(GuardarBodegaHandler::class)->handle(new GuardarBodegaCommand(
                $id,
                (string) $this->request->getPost('codigo'),
                (string) $this->request->getPost('nombre'),
                (string) $this->request->getPost('direccion'),
                $this->request->getPost('activo') !== null,
            ));
        } catch (ValidationException|NotFoundException $e) {
            return $this->render($id, $e->getMessage());
        }

        $_SESSION['bodega_aviso'] = $id === null ? 'Bodega creada.' : 'Bodega actualizada.';

        return redirect()->to('/bodegas');
    }

    /** POST bodega-activa: Trafico/Gerencia/Owner eligen con que bodega trabajar ("" = todas). */
    public function cambiarActiva(): RedirectResponse
    {
        $volver = (string) ($this->request->getPost('volver') ?: '/');
        $volver = str_starts_with($volver, '/') && !str_starts_with($volver, '//') ? $volver : '/';

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to($volver);
        }

        $activa = $this->container()->get(BodegaActivaInterface::class);

        // El personal de una bodega no puede cambiarla.
        if ($activa->fija() === null) {
            $id = trim((string) $this->request->getPost('bodega_id'));
            $existe = $id !== '' && $this->container()->get(BodegaRepositoryInterface::class)->findById($id) !== null;
            $_SESSION[BodegaActivaSesion::CLAVE] = $existe ? $id : '';
        }

        return redirect()->to($volver);
    }

    private function render(?string $editarId, ?string $error): string
    {
        if ($error !== null) {
            $this->response->setStatusCode(422);
        }

        $repo = $this->container()->get(BodegaRepositoryInterface::class);
        $aviso = $_SESSION['bodega_aviso'] ?? null;
        unset($_SESSION['bodega_aviso']);

        return view('bodegas/index', [
            'bodegas' => $repo->findAll(),
            'editar' => $editarId !== null ? $repo->findById($editarId) : null,
            'error' => $error,
            'aviso' => $aviso,
            'post' => $error !== null ? $this->request->getPost() : null,
        ]);
    }
}
