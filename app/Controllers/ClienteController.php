<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Carga\Application\ListarCargasPorCliente\ListarCargasPorClienteHandler;
use Courier\Carga\Application\ListarCargasPorCliente\ListarCargasPorClienteQuery;
use Courier\Cliente\Application\ActualizarCliente\ActualizarClienteCommand;
use Courier\Cliente\Application\ActualizarCliente\ActualizarClienteHandler;
use Courier\Cliente\Application\AprobarCliente\AprobarClienteCommand;
use Courier\Cliente\Application\AprobarCliente\AprobarClienteHandler;
use Courier\Cliente\Application\BuscarClientes\BuscarClientesHandler;
use Courier\Cliente\Application\BuscarClientes\BuscarClientesQuery;
use Courier\Cliente\Application\DesactivarCliente\DesactivarClienteCommand;
use Courier\Cliente\Application\DesactivarCliente\DesactivarClienteHandler;
use Courier\Cliente\Application\ListarClientesPendientes\ListarClientesPendientesHandler;
use Courier\Cliente\Application\ListarClientesPendientes\ListarClientesPendientesQuery;
use Courier\Cliente\Application\RechazarCliente\RechazarClienteCommand;
use Courier\Cliente\Application\RechazarCliente\RechazarClienteHandler;
use Courier\Cliente\Application\RegistrarCliente\RegistrarClienteCommand;
use Courier\Cliente\Application\RegistrarCliente\RegistrarClienteHandler;
use Courier\Cliente\Domain\ClienteDocumentoRepositoryInterface;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Pais\Application\ListarPaises\ListarPaisesHandler;
use Courier\Pais\Application\ListarPaises\ListarPaisesQuery;
use Courier\Shared\Domain\Exception\ValidationException;

final class ClienteController extends BaseController
{
    public function index(): string
    {
        $pendientes = $this->container()->get(ListarClientesPendientesHandler::class)->handle(new ListarClientesPendientesQuery());

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarClientesHandler::class)->handle(new BuscarClientesQuery('', $page, $perPage));
        $paises = $this->container()->get(ListarPaisesHandler::class)->handle(new ListarPaisesQuery());

        return view('clientes/index', [
            'clientes' => $resultado->items,
            'total' => $resultado->total,
            'page' => $resultado->page,
            'perPage' => $resultado->perPage,
            'totalPages' => $resultado->totalPages(),
            'pendientes' => $pendientes,
            'paises' => $paises,
            'error' => null,
        ]);
    }

    public function buscar(): ResponseInterface
    {
        $termino = (string) ($this->request->getGet('q') ?? '');
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarClientesHandler::class)->handle(new BuscarClientesQuery($termino, $page, $perPage));

        $html = view('clientes/_tabla_filas', ['clientes' => $resultado->items], ['debug' => false]);
        $paginacion = view('clientes/_paginacion', [
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
        $paises = $this->container()->get(ListarPaisesHandler::class)->handle(new ListarPaisesQuery());

        return view('clientes/nuevo', ['error' => null, 'paises' => $paises]);
    }

    public function store(): RedirectResponse|string
    {
        $paises = $this->container()->get(ListarPaisesHandler::class)->handle(new ListarPaisesQuery());

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('clientes/nuevo', ['error' => 'Sesion expirada, por favor intente de nuevo.', 'paises' => $paises]);
        }

        try {
            $command = new RegistrarClienteCommand(
                (string) $this->request->getPost('nombre'),
                (string) $this->request->getPost('email'),
                $this->request->getPost('telefono') !== '' ? (string) $this->request->getPost('telefono') : null,
                $this->request->getPost('direccion') !== '' ? (string) $this->request->getPost('direccion') : null,
                $this->request->getPost('apellido') !== '' ? (string) $this->request->getPost('apellido') : null,
                $this->request->getPost('empresa') !== '' ? (string) $this->request->getPost('empresa') : null,
                $this->request->getPost('pais') !== '' ? (string) $this->request->getPost('pais') : null,
            );

            $this->container()->get(RegistrarClienteHandler::class)->handle($command);

            return redirect()->to('/clientes');
        } catch (ValidationException $e) {
            return view('clientes/nuevo', ['error' => $e->getMessage(), 'paises' => $paises]);
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
            $command = new RegistrarClienteCommand(
                (string) $this->request->getPost('nombre'),
                (string) $this->request->getPost('email'),
                null,
                null,
                null,
                $this->request->getPost('empresa') !== '' ? (string) $this->request->getPost('empresa') : null,
                $this->request->getPost('pais') !== '' ? (string) $this->request->getPost('pais') : null,
            );

            $result = $this->container()->get(RegistrarClienteHandler::class)->handle($command);
            $cliente = $this->container()->get(ClienteRepositoryInterface::class)->findById($result->clienteId);

            return $this->response->setJSON([
                'success' => true,
                'cliente' => [
                    'id' => $cliente->id(),
                    'nombre' => $cliente->nombre(),
                    'email' => (string) $cliente->email(),
                    'empresa' => $cliente->empresa(),
                    'codigo' => $cliente->codigo(),
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function show(string $id): string
    {
        $cliente = $this->container()->get(ClienteRepositoryInterface::class)->findById($id);

        if ($cliente === null) {
            $this->response->setStatusCode(404);

            return 'Cliente no encontrado.';
        }

        $documentos = $this->container()->get(ClienteDocumentoRepositoryInterface::class)->findByClienteId($id);
        $cargas = $this->container()->get(ListarCargasPorClienteHandler::class)->handle(new ListarCargasPorClienteQuery($id));

        return view('clientes/show', [
            'cliente' => $cliente,
            'documentos' => $documentos,
            'cargas' => $cargas,
        ]);
    }

    public function showEditForm(string $id): string
    {
        $cliente = $this->container()->get(ClienteRepositoryInterface::class)->findById($id);

        if ($cliente === null) {
            $this->response->setStatusCode(404);

            return 'Cliente no encontrado.';
        }

        $paises = $this->container()->get(ListarPaisesHandler::class)->handle(new ListarPaisesQuery());

        return view('clientes/editar', ['cliente' => $cliente, 'paises' => $paises, 'error' => null]);
    }

    public function update(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/clientes/' . $id . '/editar');
        }

        $cliente = $this->container()->get(ClienteRepositoryInterface::class)->findById($id);

        if ($cliente === null) {
            $this->response->setStatusCode(404);

            return 'Cliente no encontrado.';
        }

        try {
            $command = new ActualizarClienteCommand(
                $id,
                (string) $this->request->getPost('nombre'),
                (string) $this->request->getPost('email'),
                $this->request->getPost('telefono') !== '' ? (string) $this->request->getPost('telefono') : null,
                $this->request->getPost('direccion') !== '' ? (string) $this->request->getPost('direccion') : null,
                $this->request->getPost('apellido') !== '' ? (string) $this->request->getPost('apellido') : null,
                $this->request->getPost('empresa') !== '' ? (string) $this->request->getPost('empresa') : null,
                $this->request->getPost('pais') !== '' ? (string) $this->request->getPost('pais') : null,
            );

            $this->container()->get(ActualizarClienteHandler::class)->handle($command);

            return redirect()->to('/clientes/' . $id);
        } catch (ValidationException $e) {
            $paises = $this->container()->get(ListarPaisesHandler::class)->handle(new ListarPaisesQuery());

            return view('clientes/editar', ['cliente' => $cliente, 'paises' => $paises, 'error' => $e->getMessage()]);
        }
    }

    public function delete(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/clientes');
        }

        $this->container()->get(DesactivarClienteHandler::class)->handle(new DesactivarClienteCommand($id));

        return redirect()->to('/clientes');
    }

    public function aprobar(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/clientes');
        }

        $this->container()->get(AprobarClienteHandler::class)->handle(new AprobarClienteCommand($id));

        return redirect()->to('/clientes');
    }

    public function rechazar(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/clientes');
        }

        $this->container()->get(RechazarClienteHandler::class)->handle(new RechazarClienteCommand($id));

        return redirect()->to('/clientes');
    }
}
