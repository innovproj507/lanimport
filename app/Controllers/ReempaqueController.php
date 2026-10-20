<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Cliente\Application\ListarClientes\ListarClientesHandler;
use Courier\Cliente\Application\ListarClientes\ListarClientesQuery;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Application\CancelarReempaque\CancelarReempaqueCommand;
use Courier\Reempaque\Application\CancelarReempaque\CancelarReempaqueHandler;
use Courier\Reempaque\Application\IniciarReempaque\IniciarReempaqueCommand;
use Courier\Reempaque\Application\IniciarReempaque\IniciarReempaqueHandler;
use Courier\Reempaque\Application\ListarReempaqueOrdenes\ListarReempaqueOrdenesHandler;
use Courier\Reempaque\Application\ListarReempaqueOrdenes\ListarReempaqueOrdenesQuery;
use Courier\Reempaque\Application\VerReempaqueOrden\VerReempaqueOrdenHandler;
use Courier\Reempaque\Application\VerReempaqueOrden\VerReempaqueOrdenQuery;
use Courier\Reempaque\Domain\TipoReempaque;
use Courier\Salida\Domain\CargasParaSalidaInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Reempaque: trafico ordena que cargas de un cliente se reempacan (y como); bodega lo
 * ejecuta en la app de la PDA caja por caja. Aqui trafico crea la orden, ve su avance e
 * imprime las etiquetas de las cajas nuevas.
 */
final class ReempaqueController extends BaseController
{
    /** "Ordenar reempaque": cliente, sus cargas con bultos listos, tipo e instruccion para bodega. */
    public function index(): string
    {
        return $this->renderOrdenar((string) ($this->request->getGet('cliente_id') ?? ''), null, [], '', '');
    }

    /** @param array<int, string> $seleccionadas */
    private function renderOrdenar(string $clienteId, ?string $error, array $seleccionadas, string $tipo, string $instrucciones): string
    {
        if ($error !== null) {
            $this->response->setStatusCode(422);
        }

        $cliente = $clienteId !== '' ? $this->container()->get(ClienteRepositoryInterface::class)->findById($clienteId) : null;
        // Las cargas del cliente en bodega (mismo listado que "Armar salida").
        $cargas = $cliente !== null
            ? array_values(array_filter(
                $this->container()->get(CargasParaSalidaInterface::class)->listar(),
                static fn (array $c) => $c['clienteId'] === $clienteId,
            ))
            : [];

        return view('reempaque/ordenar', [
            'clientes' => $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery()),
            'cliente' => $cliente,
            'cargas' => $cargas,
            'tipos' => TipoReempaque::seleccionables(),
            'seleccionadas' => $seleccionadas,
            'tipo' => $tipo,
            'instrucciones' => $instrucciones,
            'error' => $error,
        ]);
    }

    public function store(): RedirectResponse|string
    {
        $clienteId = (string) ($this->request->getPost('cliente_id') ?? '');
        $tipo = (string) ($this->request->getPost('tipo') ?? '');
        $instrucciones = trim((string) ($this->request->getPost('instrucciones') ?? ''));
        $cargaIds = $this->request->getPost('carga_ids') ?? [];
        $cargaIds = is_array($cargaIds) ? array_values(array_unique(array_map('strval', $cargaIds))) : [];

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderOrdenar($clienteId, 'Sesion expirada, intente de nuevo.', $cargaIds, $tipo, $instrucciones);
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        try {
            // Entran los bultos ya escaneados y libres de cada carga marcada.
            $lpnIds = $this->container()->get(CargasParaSalidaInterface::class)->lpnsListos($cargaIds);

            $ordenId = $this->container()->get(IniciarReempaqueHandler::class)->handle(new IniciarReempaqueCommand(
                $tipo,
                $clienteId,
                $lpnIds,
                (string) $sessionManager->currentUsuarioId(),
                $instrucciones !== '' ? $instrucciones : null,
            ));

            $_SESSION['reempaque_mensaje'] = 'Orden enviada a bodega con ' . count($lpnIds) . ' bultos. Bodega la ve en la app (Reempaque).';

            return redirect()->to('/reempaques/' . $ordenId);
        } catch (ValidationException $e) {
            return $this->renderOrdenar($clienteId, $e->getMessage(), $cargaIds, $tipo, $instrucciones);
        }
    }

    /** Avance de la orden: bultos por empacar, cajas armadas (con su contenido) e impresion de etiquetas. */
    public function show(string $id): string
    {
        $orden = $this->container()->get(VerReempaqueOrdenHandler::class)->handle(new VerReempaqueOrdenQuery($id));

        if ($orden === null) {
            $this->response->setStatusCode(404);

            return 'Orden de reempaque no encontrada.';
        }

        $lpns = $this->container()->get(LpnRepositoryInterface::class);
        $lpnsPorId = [];

        foreach (array_merge($orden->origenLpnIds(), $orden->destinoLpnIds()) as $lpnId) {
            $lpnsPorId[$lpnId] = $lpns->findById($lpnId);
        }

        $mensaje = $_SESSION['reempaque_mensaje'] ?? null;
        unset($_SESSION['reempaque_mensaje']);

        return view('reempaque/detalle', [
            'orden' => $orden,
            'cliente' => $this->container()->get(ClienteRepositoryInterface::class)->findById($orden->clienteId()),
            'lpnsPorId' => $lpnsPorId,
            'mensaje' => $mensaje,
        ]);
    }

    public function cancelar(string $id): RedirectResponse
    {
        if (Csrf::verify($this->request->getPost('_csrf'))) {
            try {
                $this->container()->get(CancelarReempaqueHandler::class)->handle(new CancelarReempaqueCommand($id));
                $_SESSION['reempaque_mensaje'] = 'Orden cancelada: los bultos vuelven a estar libres y las cajas nuevas se descartaron.';
            } catch (NotFoundException|ValidationException $e) {
                $_SESSION['reempaque_mensaje'] = $e->getMessage();
            }
        }

        return redirect()->to('/reempaques/' . $id);
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
