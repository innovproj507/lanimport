<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Aduanero\Application\ListarAduaneros\ListarAduanerosHandler;
use Courier\Aduanero\Application\ListarAduaneros\ListarAduanerosQuery;
use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Cliente\Application\ListarClientes\ListarClientesHandler;
use Courier\Cliente\Application\ListarClientes\ListarClientesQuery;
use Courier\Lpn\Application\BuscarInventario\BuscarInventarioHandler;
use Courier\Lpn\Application\BuscarInventario\BuscarInventarioQuery;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Configuracion\Domain\ConfiguracionRepositoryInterface;
use Courier\Naviera\Application\ListarNavieras\ListarNavierasHandler;
use Courier\Naviera\Application\ListarNavieras\ListarNavierasQuery;
use Courier\Salida\Application\ActualizarEmbarqueSalida\ActualizarEmbarqueSalidaCommand;
use Courier\Salida\Application\ActualizarEmbarqueSalida\ActualizarEmbarqueSalidaHandler;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Salida\Application\CancelarOrdenSalida\CancelarOrdenSalidaCommand;
use Courier\Salida\Application\CancelarOrdenSalida\CancelarOrdenSalidaHandler;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaCommand;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaHandler;
use Courier\Salida\Application\CrearOrdenSalida\CrearOrdenSalidaCommand;
use Courier\Salida\Application\CrearOrdenSalida\CrearOrdenSalidaHandler;
use Courier\Salida\Application\ListarOrdenesSalida\ListarOrdenesSalidaHandler;
use Courier\Salida\Application\ListarOrdenesSalida\ListarOrdenesSalidaQuery;
use Courier\Salida\Application\RegistrarEscaneoSalida\RegistrarEscaneoSalidaCommand;
use Courier\Salida\Application\RegistrarEscaneoSalida\RegistrarEscaneoSalidaHandler;
use Courier\Salida\Application\VerOrdenSalida\VerOrdenSalidaHandler;
use Courier\Salida\Application\VerOrdenSalida\VerOrdenSalidaQuery;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

final class SalidaController extends BaseController
{
    public function index(): string
    {
        $clienteId = (string) ($this->request->getGet('cliente_id') ?? '');
        $aduaneroIdentificador = (string) ($this->request->getGet('aduanero_identificador') ?? '');
        $ubicacionId = (string) ($this->request->getGet('ubicacion_id') ?? '');

        $query = new BuscarInventarioQuery(
            'available',
            $ubicacionId !== '' ? $ubicacionId : null,
            $clienteId !== '' ? $clienteId : null,
            null,
            $aduaneroIdentificador !== '' ? $aduaneroIdentificador : null,
        );

        $lpns = $this->container()->get(BuscarInventarioHandler::class)->handle($query);
        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());
        $aduaneros = $this->container()->get(ListarAduanerosHandler::class)->handle(new ListarAduanerosQuery());

        return view('salida/seleccion', [
            'lpns' => $lpns,
            'clientes' => $clientes,
            'aduaneros' => $aduaneros,
            'filtros' => [
                'cliente_id' => $clienteId,
                'aduanero_identificador' => $aduaneroIdentificador,
                'ubicacion_id' => $ubicacionId,
            ],
        ]);
    }

    public function store(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/salidas');
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        $clienteId = (string) ($this->request->getPost('cliente_id') ?? '');
        $aduaneroIdentificador = (string) ($this->request->getPost('aduanero_identificador') ?? '');
        $lpnIds = $this->request->getPost('lpn_ids') ?? [];
        $lpnIds = is_array($lpnIds) ? array_map('strval', $lpnIds) : [];

        try {
            $command = new CrearOrdenSalidaCommand(
                $clienteId,
                $lpnIds,
                $aduaneroIdentificador !== '' ? $aduaneroIdentificador : null,
                (string) $sessionManager->currentUsuarioId(),
            );

            $ordenId = $this->container()->get(CrearOrdenSalidaHandler::class)->handle($command);

            return redirect()->to('/salidas/' . $ordenId);
        } catch (ValidationException $e) {
            $this->response->setStatusCode(422);

            return htmlspecialchars($e->getMessage());
        }
    }

    public function show(string $id): string
    {
        $orden = $this->container()->get(VerOrdenSalidaHandler::class)->handle(new VerOrdenSalidaQuery($id));

        if ($orden === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());
        $clienteNombre = '—';

        foreach ($clientes as $cliente) {
            if ($cliente->id() === $orden->clienteId()) {
                $clienteNombre = $cliente->nombreCompleto();

                break;
            }
        }

        // Navieras activas, mas la de la orden aunque se haya desactivado despues.
        $navieras = $this->container()->get(ListarNavierasHandler::class)->handle(new ListarNavierasQuery(false));
        $navieraActual = $orden->embarque()->navieraId;
        $navieras = array_values(array_filter($navieras, static fn ($n) => $n->activo() || $n->id() === $navieraActual));

        $mensaje = $_SESSION['salida_mensaje'] ?? null;
        $error = $_SESSION['salida_error'] ?? null;
        unset($_SESSION['salida_mensaje'], $_SESSION['salida_error']);

        return view('salida/escaneo', [
            'orden' => $orden,
            'clienteNombre' => $clienteNombre,
            'navieras' => $navieras,
            'mensaje' => $mensaje,
            'error' => $error,
        ]);
    }

    public function guardarEmbarque(string $id): RedirectResponse
    {
        if (Csrf::verify($this->request->getPost('_csrf'))) {
            try {
                $this->container()->get(ActualizarEmbarqueSalidaHandler::class)->handle(new ActualizarEmbarqueSalidaCommand(
                    $id,
                    (string) $this->request->getPost('numero_contenedor'),
                    (string) $this->request->getPost('numero_sello'),
                    (string) $this->request->getPost('naviera_id'),
                    (string) $this->request->getPost('nombre_barco'),
                    (string) $this->request->getPost('fecha_cargue'),
                    (string) $this->request->getPost('fecha_salida_etd'),
                    (string) $this->request->getPost('destino_final'),
                ));
                $_SESSION['salida_mensaje'] = 'Datos del embarque guardados.';
            } catch (ValidationException|NotFoundException $e) {
                $_SESSION['salida_error'] = $e->getMessage();
            }
        }

        return redirect()->to('/salidas/' . $id);
    }

    /** Manifiesto imprimible: encabezado del embarque y una fila por carga con totales. */
    public function manifiesto(string $id): string
    {
        $orden = $this->container()->get(VerOrdenSalidaHandler::class)->handle(new VerOrdenSalidaQuery($id));

        if ($orden === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        return view('salida/manifiesto_imprimir', [
            'orden' => $orden,
            'cliente' => $this->container()->get(ClienteRepositoryInterface::class)->findById($orden->clienteId()),
            'filas' => $this->container()->get(SalidaOrdenRepositoryInterface::class)->manifiesto($orden->id()),
            'empresa' => $this->container()->get(ConfiguracionRepositoryInterface::class)->datosEmpresa(),
        ]);
    }

    public function confirmarEscaneo(string $id): ResponseInterface
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->response->setStatusCode(419, 'Page Expired')->setJSON([
                'success' => false,
                'message' => 'Sesion expirada, por favor recargue la pagina.',
            ]);
        }

        try {
            $codigo = (string) ($this->request->getPost('codigo') ?? '');
            $command = new RegistrarEscaneoSalidaCommand($id, $codigo);
            $progreso = $this->container()->get(RegistrarEscaneoSalidaHandler::class)->handle($command);

            return $this->response->setJSON([
                'success' => true,
                'codigo' => $codigo,
                'progreso' => $progreso,
            ]);
        } catch (NotFoundException|ValidationException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function finalizar(string $id): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/salidas/' . $id);
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        try {
            $command = new ConfirmarSalidaCommand($id, (string) $sessionManager->currentUsuarioId());
            $this->container()->get(ConfirmarSalidaHandler::class)->handle($command);

            return redirect()->to('/salidas/' . $id);
        } catch (NotFoundException|ValidationException) {
            return $this->show($id);
        }
    }

    public function cancelar(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/salidas');
        }

        try {
            $this->container()->get(CancelarOrdenSalidaHandler::class)->handle(new CancelarOrdenSalidaCommand($id));
        } catch (NotFoundException|ValidationException) {
            // si la orden ya no puede cancelarse, simplemente se vuelve al listado
        }

        return redirect()->to('/salidas');
    }

    public function historial(): string
    {
        $estado = (string) ($this->request->getGet('estado') ?? '');
        $clienteId = (string) ($this->request->getGet('cliente_id') ?? '');

        $ordenes = $this->container()->get(ListarOrdenesSalidaHandler::class)->handle(new ListarOrdenesSalidaQuery(
            $estado !== '' ? $estado : null,
            $clienteId !== '' ? $clienteId : null,
        ));

        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());
        $mapaClientes = [];

        foreach ($clientes as $cliente) {
            $mapaClientes[$cliente->id()] = $cliente->nombreCompleto();
        }

        return view('salida/historial', [
            'ordenes' => $ordenes,
            'mapaClientes' => $mapaClientes,
            'clientes' => $clientes,
            'filtros' => ['estado' => $estado, 'cliente_id' => $clienteId],
        ]);
    }
}
