<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Aduanero\Application\ActualizarAduanero\ActualizarAduaneroCommand;
use Courier\Aduanero\Application\ActualizarAduanero\ActualizarAduaneroHandler;
use Courier\Aduanero\Application\BuscarAduaneros\BuscarAduanerosHandler;
use Courier\Aduanero\Application\BuscarAduaneros\BuscarAduanerosQuery;
use Courier\Aduanero\Application\RegistrarAduanero\RegistrarAduaneroCommand;
use Courier\Aduanero\Application\RegistrarAduanero\RegistrarAduaneroHandler;
use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Pais\Application\ListarPaises\ListarPaisesHandler;
use Courier\Pais\Application\ListarPaises\ListarPaisesQuery;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class AduaneroController extends BaseController
{
    public function index(): string
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = $this->perPageDesdeRequest();

        $resultado = $this->container()->get(BuscarAduanerosHandler::class)->handle(new BuscarAduanerosQuery('', $page, $perPage));

        return view('aduaneros/index', [
            'aduaneros' => $resultado->items,
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

        $resultado = $this->container()->get(BuscarAduanerosHandler::class)->handle(new BuscarAduanerosQuery($termino, $page, $perPage));

        $html = view('aduaneros/_tabla_filas', ['aduaneros' => $resultado->items], ['debug' => false]);
        $paginacion = view('aduaneros/_paginacion', [
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

        return view('aduaneros/nuevo', ['error' => null, 'paises' => $paises]);
    }

    public function store(): RedirectResponse|string
    {
        $paises = $this->container()->get(ListarPaisesHandler::class)->handle(new ListarPaisesQuery());

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return view('aduaneros/nuevo', ['error' => 'Sesion expirada, por favor intente de nuevo.', 'paises' => $paises]);
        }

        try {
            $command = new RegistrarAduaneroCommand(
                (string) $this->request->getPost('nombre'),
                $this->request->getPost('pais') !== '' ? (string) $this->request->getPost('pais') : null,
            );

            $this->container()->get(RegistrarAduaneroHandler::class)->handle($command);

            return redirect()->to('/aduaneros');
        } catch (ValidationException $e) {
            return view('aduaneros/nuevo', ['error' => $e->getMessage(), 'paises' => $paises]);
        }
    }

    public function showEditForm(string $id): string
    {
        $aduanero = $this->container()->get(AduaneroRepositoryInterface::class)->findById($id);

        if ($aduanero === null) {
            $this->response->setStatusCode(404);

            return 'Aduanero no encontrado.';
        }

        $paises = $this->container()->get(ListarPaisesHandler::class)->handle(new ListarPaisesQuery());

        return view('aduaneros/editar', ['aduanero' => $aduanero, 'paises' => $paises, 'error' => null]);
    }

    public function update(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/aduaneros/' . $id . '/editar');
        }

        try {
            $command = new ActualizarAduaneroCommand(
                $id,
                (string) $this->request->getPost('nombre'),
                $this->request->getPost('pais') !== '' ? (string) $this->request->getPost('pais') : null,
            );
            $this->container()->get(ActualizarAduaneroHandler::class)->handle($command);

            return redirect()->to('/aduaneros');
        } catch (NotFoundException $e) {
            $this->response->setStatusCode(404);

            return $e->getMessage();
        } catch (ValidationException $e) {
            $aduanero = $this->container()->get(AduaneroRepositoryInterface::class)->findById($id);
            $paises = $this->container()->get(ListarPaisesHandler::class)->handle(new ListarPaisesQuery());

            return view('aduaneros/editar', ['aduanero' => $aduanero, 'paises' => $paises, 'error' => $e->getMessage()]);
        }
    }
}
