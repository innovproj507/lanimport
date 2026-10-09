<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Lpn\Application\ActivarUbicacion\ActivarUbicacionCommand;
use Courier\Lpn\Application\ActivarUbicacion\ActivarUbicacionHandler;
use Courier\Lpn\Application\ActualizarUbicacion\ActualizarUbicacionCommand;
use Courier\Lpn\Application\ActualizarUbicacion\ActualizarUbicacionHandler;
use Courier\Lpn\Application\BuscarUbicaciones\BuscarUbicacionesHandler;
use Courier\Lpn\Application\BuscarUbicaciones\BuscarUbicacionesQuery;
use Courier\Lpn\Application\DesactivarUbicacion\DesactivarUbicacionCommand;
use Courier\Lpn\Application\DesactivarUbicacion\DesactivarUbicacionHandler;
use Courier\Lpn\Application\RegistrarUbicacion\RegistrarUbicacionCommand;
use Courier\Lpn\Application\RegistrarUbicacion\RegistrarUbicacionHandler;
use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class UbicacionController extends BaseController
{
    public function index(): string
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarUbicacionesHandler::class)->handle(new BuscarUbicacionesQuery('', $page, $perPage));

        return view('ubicaciones/index', [
            'ubicaciones' => $resultado->items,
            'total' => $resultado->total,
            'page' => $resultado->page,
            'perPage' => $resultado->perPage,
            'totalPages' => $resultado->totalPages(),
        ]);
    }

    public function buscar(): ResponseInterface
    {
        $termino = (string) ($this->request->getGet('q') ?? '');
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarUbicacionesHandler::class)->handle(new BuscarUbicacionesQuery($termino, $page, $perPage));

        $html = view('ubicaciones/_tabla_filas', ['ubicaciones' => $resultado->items], ['debug' => false]);
        $paginacion = view('ubicaciones/_paginacion', [
            'total' => $resultado->total,
            'page' => $resultado->page,
            'perPage' => $resultado->perPage,
            'totalPages' => $resultado->totalPages(),
        ], ['debug' => false]);

        return $this->response->setJSON(['html' => $html, 'paginacion' => $paginacion]);
    }

    private function perPageDesdeRequest(): int
    {
        $perPage = (int) ($this->request->getGet('per_page') ?? 10);

        return in_array($perPage, [5, 10, 25], true) ? $perPage : 10;
    }

    public function showCreateForm(): string
    {
        return view('ubicaciones/nuevo', ['error' => null]);
    }

    public function store(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('ubicaciones/nuevo', ['error' => 'Sesion expirada, por favor intente de nuevo.']);
        }

        try {
            $command = new RegistrarUbicacionCommand(
                (string) $this->request->getPost('codigo'),
                $this->request->getPost('zona') !== '' ? (string) $this->request->getPost('zona') : null,
                $this->request->getPost('pasillo') !== '' ? (string) $this->request->getPost('pasillo') : null,
                $this->request->getPost('nivel') !== '' ? (string) $this->request->getPost('nivel') : null,
            );

            $this->container()->get(RegistrarUbicacionHandler::class)->handle($command);

            return redirect()->to('/ubicaciones');
        } catch (ValidationException $e) {
            return view('ubicaciones/nuevo', ['error' => $e->getMessage()]);
        }
    }

    public function showEditForm(string $id): string
    {
        $ubicacion = $this->container()->get(UbicacionRepositoryInterface::class)->findById($id);

        if ($ubicacion === null) {
            $this->response->setStatusCode(404);

            return 'Ubicacion no encontrada.';
        }

        return view('ubicaciones/editar', ['ubicacion' => $ubicacion, 'error' => null]);
    }

    public function update(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/ubicaciones/' . $id . '/editar');
        }

        try {
            $command = new ActualizarUbicacionCommand(
                $id,
                $this->request->getPost('zona') !== '' ? (string) $this->request->getPost('zona') : null,
                $this->request->getPost('pasillo') !== '' ? (string) $this->request->getPost('pasillo') : null,
                $this->request->getPost('nivel') !== '' ? (string) $this->request->getPost('nivel') : null,
            );

            $this->container()->get(ActualizarUbicacionHandler::class)->handle($command);

            return redirect()->to('/ubicaciones');
        } catch (NotFoundException $e) {
            $this->response->setStatusCode(404);

            return $e->getMessage();
        } catch (ValidationException $e) {
            $ubicacion = $this->container()->get(UbicacionRepositoryInterface::class)->findById($id);

            return view('ubicaciones/editar', ['ubicacion' => $ubicacion, 'error' => $e->getMessage()]);
        }
    }

    public function activar(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/ubicaciones');
        }

        $this->container()->get(ActivarUbicacionHandler::class)->handle(new ActivarUbicacionCommand($id));

        return redirect()->to('/ubicaciones');
    }

    public function desactivar(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/ubicaciones');
        }

        $this->container()->get(DesactivarUbicacionHandler::class)->handle(new DesactivarUbicacionCommand($id));

        return redirect()->to('/ubicaciones');
    }
}
