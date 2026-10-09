<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Cliente\Application\ListarClientes\ListarClientesHandler;
use Courier\Cliente\Application\ListarClientes\ListarClientesQuery;
use Courier\Lpn\Application\BuscarInventario\BuscarInventarioHandler;
use Courier\Lpn\Application\BuscarInventario\BuscarInventarioQuery;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesHandler;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesQuery;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Application\CancelarReempaque\CancelarReempaqueCommand;
use Courier\Reempaque\Application\CancelarReempaque\CancelarReempaqueHandler;
use Courier\Reempaque\Application\CompletarReempaque\CompletarReempaqueCommand;
use Courier\Reempaque\Application\CompletarReempaque\CompletarReempaqueHandler;
use Courier\Reempaque\Application\ConsultarGenealogiaLpn\ConsultarGenealogiaLpnHandler;
use Courier\Reempaque\Application\ConsultarGenealogiaLpn\ConsultarGenealogiaLpnQuery;
use Courier\Reempaque\Application\IniciarReempaque\IniciarReempaqueCommand;
use Courier\Reempaque\Application\IniciarReempaque\IniciarReempaqueHandler;
use Courier\Reempaque\Application\ListarReempaqueOrdenes\ListarReempaqueOrdenesHandler;
use Courier\Reempaque\Application\ListarReempaqueOrdenes\ListarReempaqueOrdenesQuery;
use Courier\Reempaque\Application\VerReempaqueOrden\VerReempaqueOrdenHandler;
use Courier\Reempaque\Application\VerReempaqueOrden\VerReempaqueOrdenQuery;
use Courier\Reempaque\Domain\TipoReempaque;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class ReempaqueController extends BaseController
{
    public function index(): string
    {
        $clienteId = (string) ($this->request->getGet('cliente_id') ?? '');

        $lpns = $clienteId !== ''
            ? $this->container()->get(BuscarInventarioHandler::class)->handle(new BuscarInventarioQuery('available', null, $clienteId))
            : [];

        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());

        return view('reempaque/seleccion', [
            'lpns' => $lpns,
            'clientes' => $clientes,
            'tipos' => TipoReempaque::cases(),
            'filtros' => ['cliente_id' => $clienteId],
        ]);
    }

    public function store(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/reempaques');
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        $clienteId = (string) ($this->request->getPost('cliente_id') ?? '');
        $tipo = (string) ($this->request->getPost('tipo') ?? '');
        $lpnIds = $this->request->getPost('lpn_ids') ?? [];
        $lpnIds = is_array($lpnIds) ? array_map('strval', $lpnIds) : [];

        try {
            $command = new IniciarReempaqueCommand(
                $tipo,
                $clienteId,
                $lpnIds,
                (string) $sessionManager->currentUsuarioId(),
            );

            $ordenId = $this->container()->get(IniciarReempaqueHandler::class)->handle($command);

            return redirect()->to('/reempaques/' . $ordenId);
        } catch (ValidationException $e) {
            $this->response->setStatusCode(422);

            return htmlspecialchars($e->getMessage());
        }
    }

    public function show(string $id): string
    {
        $orden = $this->container()->get(VerReempaqueOrdenHandler::class)->handle(new VerReempaqueOrdenQuery($id));

        if ($orden === null) {
            $this->response->setStatusCode(404);

            return 'Orden de reempaque no encontrada.';
        }

        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());
        $clienteNombre = '—';

        foreach ($clientes as $cliente) {
            if ($cliente->id() === $orden->clienteId()) {
                $clienteNombre = $cliente->nombreCompleto();

                break;
            }
        }

        $ubicaciones = $this->container()->get(ListarUbicacionesHandler::class)->handle(new ListarUbicacionesQuery());
        $lpnRepository = $this->container()->get(LpnRepositoryInterface::class);

        $lpnsPorId = [];

        foreach (array_merge($orden->origenLpnIds(), $orden->destinoLpnIds()) as $lpnId) {
            $lpnsPorId[$lpnId] = $lpnRepository->findById($lpnId);
        }

        $genealogiaPorDestino = [];

        foreach ($orden->destinoLpnIds() as $destinoId) {
            $genealogiaPorDestino[$destinoId] = $this->container()->get(ConsultarGenealogiaLpnHandler::class)
                ->handle(new ConsultarGenealogiaLpnQuery($destinoId));
        }

        return view('reempaque/detalle', [
            'orden' => $orden,
            'clienteNombre' => $clienteNombre,
            'ubicaciones' => $ubicaciones,
            'lpnsPorId' => $lpnsPorId,
            'genealogiaPorDestino' => $genealogiaPorDestino,
        ]);
    }

    public function completar(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/reempaques/' . $id);
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        $cantidadDestinos = (int) ($this->request->getPost('cantidad_destinos') ?? '1');
        $ubicacionId = (string) ($this->request->getPost('ubicacion_id') ?? '');

        try {
            $command = new CompletarReempaqueCommand(
                $id,
                $cantidadDestinos,
                $ubicacionId !== '' ? $ubicacionId : null,
                (string) $sessionManager->currentUsuarioId(),
            );

            $this->container()->get(CompletarReempaqueHandler::class)->handle($command);
        } catch (NotFoundException|ValidationException) {
            // si la orden ya no esta pendiente, simplemente se vuelve a mostrar su estado actual
        }

        return redirect()->to('/reempaques/' . $id);
    }

    public function cancelar(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/reempaques');
        }

        try {
            $this->container()->get(CancelarReempaqueHandler::class)->handle(new CancelarReempaqueCommand($id));
        } catch (NotFoundException|ValidationException) {
            // si la orden ya no puede cancelarse, simplemente se vuelve al listado
        }

        return redirect()->to('/reempaques');
    }

    public function historial(): string
    {
        $estado = (string) ($this->request->getGet('estado') ?? '');
        $clienteId = (string) ($this->request->getGet('cliente_id') ?? '');

        $ordenes = $this->container()->get(ListarReempaqueOrdenesHandler::class)->handle(new ListarReempaqueOrdenesQuery(
            $estado !== '' ? $estado : null,
            $clienteId !== '' ? $clienteId : null,
        ));

        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());
        $mapaClientes = [];

        foreach ($clientes as $cliente) {
            $mapaClientes[$cliente->id()] = $cliente->nombreCompleto();
        }

        return view('reempaque/historial', [
            'ordenes' => $ordenes,
            'mapaClientes' => $mapaClientes,
            'clientes' => $clientes,
            'filtros' => ['estado' => $estado, 'cliente_id' => $clienteId],
        ]);
    }
}
