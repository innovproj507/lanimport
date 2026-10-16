<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Naviera\Application\ActualizarNaviera\ActualizarNavieraCommand;
use Courier\Naviera\Application\ActualizarNaviera\ActualizarNavieraHandler;
use Courier\Naviera\Application\BuscarNavieras\BuscarNavierasHandler;
use Courier\Naviera\Application\EliminarNaviera\EliminarNavieraCommand;
use Courier\Naviera\Application\EliminarNaviera\EliminarNavieraHandler;
use Courier\Naviera\Application\BuscarNavieras\BuscarNavierasQuery;
use Courier\Naviera\Application\RegistrarNaviera\RegistrarNavieraCommand;
use Courier\Naviera\Application\RegistrarNaviera\RegistrarNavieraHandler;
use Courier\Naviera\Domain\NavieraRepositoryInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

/** Catalogo de navieras (mismo patron de listado/busqueda que ProveedorController). */
final class NavieraController extends BaseController
{
    public function index(): string
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarNavierasHandler::class)->handle(new BuscarNavierasQuery('', $page, $perPage));

        return view('navieras/index', [
            'navieras' => $resultado->items,
            'total' => $resultado->total,
            'page' => $resultado->page,
            'perPage' => $resultado->perPage,
            'totalPages' => $resultado->totalPages(),
            'flash' => $this->tomarFlash(),
        ]);
    }

    /** Elimina solo si la naviera nunca se uso en salidas; si se uso avisa que se desactive (gerencia/owner). */
    public function eliminar(string $id): RedirectResponse
    {
        if (Csrf::verify($this->request->getPost('_csrf'))) {
            try {
                $nombre = $this->container()->get(EliminarNavieraHandler::class)->handle(new EliminarNavieraCommand($id));
                $this->flash('ok', "Naviera {$nombre} eliminada.");
            } catch (ValidationException|NotFoundException $e) {
                $this->flash('error', $e->getMessage());
            }
        }

        return redirect()->to('/navieras');
    }

    public function activar(string $id): RedirectResponse
    {
        return $this->cambiarEstado($id, true);
    }

    public function desactivar(string $id): RedirectResponse
    {
        return $this->cambiarEstado($id, false);
    }

    private function cambiarEstado(string $id, bool $activo): RedirectResponse
    {
        $naviera = $this->container()->get(NavieraRepositoryInterface::class)->findById($id);

        if ($naviera !== null && Csrf::verify($this->request->getPost('_csrf'))) {
            $this->container()->get(ActualizarNavieraHandler::class)->handle(new ActualizarNavieraCommand($id, $naviera->nombre(), $activo));
            $this->flash('ok', $activo ? "Naviera {$naviera->nombre()} activada." : "Naviera {$naviera->nombre()} desactivada: ya no aparece en salidas nuevas.");
        }

        return redirect()->to('/navieras');
    }

    public function buscar(): ResponseInterface
    {
        $termino = (string) ($this->request->getGet('q') ?? '');
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarNavierasHandler::class)->handle(new BuscarNavierasQuery($termino, $page, $perPage));

        $html = view('navieras/_tabla_filas', ['navieras' => $resultado->items], ['debug' => false]);
        $paginacion = view('navieras/_paginacion', [
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
        return view('navieras/nuevo', ['error' => null, 'nombre' => '']);
    }

    public function store(): RedirectResponse|string
    {
        $nombre = (string) $this->request->getPost('nombre');

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('navieras/nuevo', ['error' => 'Sesion expirada, por favor intente de nuevo.', 'nombre' => $nombre]);
        }

        try {
            $this->container()->get(RegistrarNavieraHandler::class)->handle(new RegistrarNavieraCommand($nombre));

            return redirect()->to('/navieras');
        } catch (ValidationException $e) {
            return view('navieras/nuevo', ['error' => $e->getMessage(), 'nombre' => $nombre]);
        }
    }

    public function showEditForm(string $id): string
    {
        $naviera = $this->container()->get(NavieraRepositoryInterface::class)->findById($id);

        if ($naviera === null) {
            $this->response->setStatusCode(404);

            return 'Naviera no encontrada.';
        }

        return view('navieras/editar', ['naviera' => $naviera, 'error' => null]);
    }

    public function update(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/navieras/' . $id . '/editar');
        }

        try {
            $this->container()->get(ActualizarNavieraHandler::class)->handle(new ActualizarNavieraCommand(
                $id,
                (string) $this->request->getPost('nombre'),
                $this->request->getPost('activo') !== null,
            ));

            return redirect()->to('/navieras');
        } catch (NotFoundException $e) {
            $this->response->setStatusCode(404);

            return $e->getMessage();
        } catch (ValidationException $e) {
            $naviera = $this->container()->get(NavieraRepositoryInterface::class)->findById($id);

            return view('navieras/editar', ['naviera' => $naviera, 'error' => $e->getMessage()]);
        }
    }
}
