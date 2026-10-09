<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Proveedor\Application\ActualizarProveedor\ActualizarProveedorCommand;
use Courier\Proveedor\Application\ActualizarProveedor\ActualizarProveedorHandler;
use Courier\Proveedor\Application\BuscarProveedores\BuscarProveedoresHandler;
use Courier\Proveedor\Application\BuscarProveedores\BuscarProveedoresQuery;
use Courier\Proveedor\Application\RegistrarProveedor\RegistrarProveedorCommand;
use Courier\Proveedor\Application\RegistrarProveedor\RegistrarProveedorHandler;
use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class ProveedorController extends BaseController
{
    public function index(): string
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarProveedoresHandler::class)->handle(new BuscarProveedoresQuery('', $page, $perPage));

        return view('proveedores/index', [
            'proveedores' => $resultado->items,
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

        $resultado = $this->container()->get(BuscarProveedoresHandler::class)->handle(new BuscarProveedoresQuery($termino, $page, $perPage));

        $html = view('proveedores/_tabla_filas', ['proveedores' => $resultado->items], ['debug' => false]);
        $paginacion = view('proveedores/_paginacion', [
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
        return view('proveedores/nuevo', ['error' => null]);
    }

    public function store(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('proveedores/nuevo', ['error' => 'Sesion expirada, por favor intente de nuevo.']);
        }

        try {
            $command = new RegistrarProveedorCommand(
                (string) $this->request->getPost('identificador'),
                (string) $this->request->getPost('nombre'),
            );

            $this->container()->get(RegistrarProveedorHandler::class)->handle($command);

            return redirect()->to('/proveedores');
        } catch (ValidationException $e) {
            return view('proveedores/nuevo', ['error' => $e->getMessage()]);
        }
    }

    public function storeRapido(): ResponseInterface
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->response->setStatusCode(419)->setJSON([
                'success' => false,
                'message' => 'Sesion expirada, por favor recargue la pagina.',
            ]);
        }

        try {
            $command = new RegistrarProveedorCommand(
                (string) $this->request->getPost('identificador'),
                (string) $this->request->getPost('nombre'),
            );

            $this->container()->get(RegistrarProveedorHandler::class)->handle($command);

            return $this->response->setJSON([
                'success' => true,
                'proveedor' => [
                    'identificador' => $command->identificador,
                    'nombre' => $command->nombre,
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function showEditForm(string $id): string
    {
        $proveedor = $this->container()->get(ProveedorRepositoryInterface::class)->findById($id);

        if ($proveedor === null) {
            $this->response->setStatusCode(404);

            return 'Proveedor no encontrado.';
        }

        return view('proveedores/editar', ['proveedor' => $proveedor, 'error' => null]);
    }

    public function update(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/proveedores/' . $id . '/editar');
        }

        try {
            $command = new ActualizarProveedorCommand($id, (string) $this->request->getPost('nombre'));
            $this->container()->get(ActualizarProveedorHandler::class)->handle($command);

            return redirect()->to('/proveedores');
        } catch (NotFoundException $e) {
            $this->response->setStatusCode(404);

            return $e->getMessage();
        } catch (ValidationException $e) {
            $proveedor = $this->container()->get(ProveedorRepositoryInterface::class)->findById($id);

            return view('proveedores/editar', ['proveedor' => $proveedor, 'error' => $e->getMessage()]);
        }
    }
}
