<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Auth\Application\ActivarUsuario\ActivarUsuarioCommand;
use Courier\Auth\Application\ActivarUsuario\ActivarUsuarioHandler;
use Courier\Auth\Application\ActualizarUsuario\ActualizarUsuarioCommand;
use Courier\Auth\Application\ActualizarUsuario\ActualizarUsuarioHandler;
use Courier\Auth\Application\BuscarUsuarios\BuscarUsuariosHandler;
use Courier\Auth\Application\BuscarUsuarios\BuscarUsuariosQuery;
use Courier\Auth\Application\DesactivarUsuario\DesactivarUsuarioCommand;
use Courier\Auth\Application\DesactivarUsuario\DesactivarUsuarioHandler;
use Courier\Auth\Application\RegistrarUsuario\RegistrarUsuarioCommand;
use Courier\Auth\Application\RegistrarUsuario\RegistrarUsuarioHandler;
use Courier\Auth\Domain\UsuarioRepositoryInterface;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class UsuarioController extends BaseController
{
    public function index(): string
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarUsuariosHandler::class)->handle(new BuscarUsuariosQuery('', $page, $perPage));

        return view('usuarios/index', [
            'usuarios' => $resultado->items,
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

        $resultado = $this->container()->get(BuscarUsuariosHandler::class)->handle(new BuscarUsuariosQuery($termino, $page, $perPage));

        $html = view('usuarios/_tabla_filas', ['usuarios' => $resultado->items], ['debug' => false]);
        $paginacion = view('usuarios/_paginacion', [
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
        return view('usuarios/nuevo', ['error' => null]);
    }

    public function store(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('usuarios/nuevo', ['error' => 'Sesion expirada, por favor intente de nuevo.']);
        }

        try {
            $command = new RegistrarUsuarioCommand(
                (string) $this->request->getPost('nombre'),
                (string) $this->request->getPost('email'),
                (string) $this->request->getPost('password'),
                (string) $this->request->getPost('rol'),
            );

            $this->container()->get(RegistrarUsuarioHandler::class)->handle($command);

            return redirect()->to('/usuarios');
        } catch (ValidationException $e) {
            return view('usuarios/nuevo', ['error' => $e->getMessage()]);
        }
    }

    public function showEditForm(string $id): string
    {
        $usuario = $this->container()->get(UsuarioRepositoryInterface::class)->findById($id);

        if ($usuario === null) {
            $this->response->setStatusCode(404);

            return 'Usuario no encontrado.';
        }

        return view('usuarios/editar', ['usuario' => $usuario, 'error' => null]);
    }

    public function update(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/usuarios/' . $id . '/editar');
        }

        try {
            $command = new ActualizarUsuarioCommand(
                $id,
                (string) $this->request->getPost('nombre'),
                (string) $this->request->getPost('rol'),
                $this->request->getPost('password') !== '' ? (string) $this->request->getPost('password') : null,
            );

            $this->container()->get(ActualizarUsuarioHandler::class)->handle($command);

            return redirect()->to('/usuarios');
        } catch (NotFoundException $e) {
            $this->response->setStatusCode(404);

            return $e->getMessage();
        } catch (ValidationException $e) {
            $usuario = $this->container()->get(UsuarioRepositoryInterface::class)->findById($id);

            return view('usuarios/editar', ['usuario' => $usuario, 'error' => $e->getMessage()]);
        }
    }

    public function activar(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/usuarios');
        }

        $this->container()->get(ActivarUsuarioHandler::class)->handle(new ActivarUsuarioCommand($id));

        return redirect()->to('/usuarios');
    }

    public function desactivar(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/usuarios');
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        if ($sessionManager->currentUsuarioId() === $id) {
            return redirect()->to('/usuarios');
        }

        $this->container()->get(DesactivarUsuarioHandler::class)->handle(new DesactivarUsuarioCommand($id));

        return redirect()->to('/usuarios');
    }
}
