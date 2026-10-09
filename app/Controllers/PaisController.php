<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Pais\Application\ActualizarPais\ActualizarPaisCommand;
use Courier\Pais\Application\ActualizarPais\ActualizarPaisHandler;
use Courier\Pais\Application\BuscarPaises\BuscarPaisesHandler;
use Courier\Pais\Application\BuscarPaises\BuscarPaisesQuery;
use Courier\Pais\Application\RegistrarPais\RegistrarPaisCommand;
use Courier\Pais\Application\RegistrarPais\RegistrarPaisHandler;
use Courier\Pais\Domain\PaisRepositoryInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class PaisController extends BaseController
{
    public function index(): string
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarPaisesHandler::class)->handle(new BuscarPaisesQuery('', $page, $perPage));

        return view('paises/index', [
            'paises' => $resultado->items,
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

        $resultado = $this->container()->get(BuscarPaisesHandler::class)->handle(new BuscarPaisesQuery($termino, $page, $perPage));

        $html = view('paises/_tabla_filas', ['paises' => $resultado->items], ['debug' => false]);
        $paginacion = view('paises/_paginacion', [
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
        return view('paises/nuevo', ['error' => null]);
    }

    public function store(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('paises/nuevo', ['error' => 'Sesion expirada, por favor intente de nuevo.']);
        }

        try {
            $command = new RegistrarPaisCommand(
                (string) $this->request->getPost('identificador'),
                (string) $this->request->getPost('nombre'),
                $this->request->getPost('mostrar_serie_etiqueta') !== null,
            );

            $this->container()->get(RegistrarPaisHandler::class)->handle($command);

            return redirect()->to('/paises');
        } catch (ValidationException $e) {
            return view('paises/nuevo', ['error' => $e->getMessage()]);
        }
    }

    public function showEditForm(string $id): string
    {
        $pais = $this->container()->get(PaisRepositoryInterface::class)->findById($id);

        if ($pais === null) {
            $this->response->setStatusCode(404);

            return 'Pais no encontrado.';
        }

        return view('paises/editar', ['pais' => $pais, 'error' => null]);
    }

    public function update(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/paises/' . $id . '/editar');
        }

        try {
            $command = new ActualizarPaisCommand(
                $id,
                (string) $this->request->getPost('nombre'),
                $this->request->getPost('mostrar_serie_etiqueta') !== null,
            );
            $this->container()->get(ActualizarPaisHandler::class)->handle($command);

            return redirect()->to('/paises');
        } catch (NotFoundException $e) {
            $this->response->setStatusCode(404);

            return $e->getMessage();
        } catch (ValidationException $e) {
            $pais = $this->container()->get(PaisRepositoryInterface::class)->findById($id);

            return view('paises/editar', ['pais' => $pais, 'error' => $e->getMessage()]);
        }
    }
}
