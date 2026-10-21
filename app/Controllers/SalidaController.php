<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnHandler;
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
use Courier\Carga\Infrastructure\Importacion\EscritorXlsx;
use Courier\Evidencia\Domain\FotoEvidenciaRepositoryInterface;
use Courier\Evidencia\Domain\TipoEvidencia;
use Courier\Naviera\Application\ListarNavieras\ListarNavierasHandler;
use Courier\Naviera\Application\ListarNavieras\ListarNavierasQuery;
use Courier\Salida\Application\ActualizarEmbarqueSalida\ActualizarEmbarqueSalidaCommand;
use Courier\Salida\Application\ActualizarEmbarqueSalida\ActualizarEmbarqueSalidaHandler;
use Courier\Salida\Domain\CargasParaSalidaInterface;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Salida\Application\CancelarOrdenSalida\CancelarOrdenSalidaCommand;
use Courier\Salida\Application\CancelarOrdenSalida\CancelarOrdenSalidaHandler;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaCommand;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaHandler;
use Courier\Salida\Application\CrearOrdenSalida\CrearOrdenSalidaCommand;
use Courier\Salida\Application\AgregarCargasSalida\AgregarCargasSalidaCommand;
use Courier\Salida\Application\AgregarCargasSalida\AgregarCargasSalidaHandler;
use Courier\Salida\Domain\SalidaOrden;
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
    /**
     * "Armar salida": trafico elige el contenedor y las cargas que suben. Se ven todas las
     * cargas en bodega; las que aun no tienen bultos listos aparecen deshabilitadas con el motivo.
     */
    public function index(): string
    {
        return $this->renderArmar(null, [], []);
    }

    /**
     * @param array<int, string> $seleccionadas cargas marcadas (para no perderlas si hay un error)
     * @param array<string, string> $embarque datos del contenedor ya escritos
     * @param SalidaOrden|null $ordenDestino si viene, se agregan cargas a esa salida en vez de crear otra
     */
    private function renderArmar(?string $error, array $seleccionadas, array $embarque, ?SalidaOrden $ordenDestino = null): string
    {
        if ($error !== null) {
            $this->response->setStatusCode(422);
        }

        return view('salida/armar', [
            'cargas' => $this->container()->get(CargasParaSalidaInterface::class)->listar(),
            'cajas' => $this->container()->get(CargasParaSalidaInterface::class)->cajasListas(),
            'navieras' => $this->container()->get(ListarNavierasHandler::class)->handle(new ListarNavierasQuery(true)),
            'seleccionadas' => $seleccionadas,
            'embarque' => $embarque,
            'ordenDestino' => $ordenDestino,
            'error' => $error,
        ]);
    }

    /** Agregar cargas a una salida pendiente: la misma lista de "Armar salida", sin el contenedor. */
    public function agregarForm(string $id): RedirectResponse|string
    {
        $orden = $this->container()->get(SalidaOrdenRepositoryInterface::class)->findById($id);

        if ($orden === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        if ($orden->estado()->value !== 'pendiente') {
            $_SESSION['salida_error'] = "La salida {$orden->etiquetaDo()} esta " . mb_strtolower($orden->estado()->label()) . ": ya no se le pueden agregar cargas.";

            return redirect()->to('/salidas/' . $orden->id());
        }

        return $this->renderArmar(null, [], [], $orden);
    }

    public function agregar(string $id): RedirectResponse|string
    {
        $orden = $this->container()->get(SalidaOrdenRepositoryInterface::class)->findById($id);

        if ($orden === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        $cargaIds = $this->request->getPost('carga_ids') ?? [];
        $cargaIds = is_array($cargaIds) ? array_values(array_unique(array_map('strval', $cargaIds))) : [];
        $cajaIds = $this->request->getPost('caja_ids') ?? [];
        $cajaIds = is_array($cajaIds) ? array_values(array_unique(array_map('strval', $cajaIds))) : [];

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderArmar('Sesion expirada, intente de nuevo.', array_merge($cargaIds, $cajaIds), [], $orden);
        }

        try {
            $lpnIds = array_merge($this->container()->get(CargasParaSalidaInterface::class)->lpnsListos($cargaIds), $cajaIds);
            $bultos = $this->container()->get(AgregarCargasSalidaHandler::class)->handle(new AgregarCargasSalidaCommand($orden->id(), $lpnIds));

            $_SESSION['salida_mensaje'] = "Se agregaron {$bultos} bultos (" . count($cargaIds) . ' carga(s)' . ($cajaIds !== [] ? ' y ' . count($cajaIds) . ' caja(s) reempacada(s)' : '') . ") a {$orden->etiquetaDo()}. Bodega debe escanearlos antes de finalizar.";

            return redirect()->to('/salidas/' . $orden->id());
        } catch (ValidationException $e) {
            return $this->renderArmar($e->getMessage(), array_merge($cargaIds, $cajaIds), [], $orden);
        }
    }

    public function store(): RedirectResponse|string
    {
        $cargaIds = $this->request->getPost('carga_ids') ?? [];
        $cargaIds = is_array($cargaIds) ? array_values(array_unique(array_map('strval', $cargaIds))) : [];
        // Cajas/paletas reempacadas marcadas: entran tal cual (su LPN).
        $cajaIds = $this->request->getPost('caja_ids') ?? [];
        $cajaIds = is_array($cajaIds) ? array_values(array_unique(array_map('strval', $cajaIds))) : [];
        $embarque = [];

        foreach (['numero_contenedor', 'numero_sello', 'naviera_id', 'nombre_barco', 'fecha_cargue', 'fecha_salida_etd', 'destino_final'] as $campo) {
            $embarque[$campo] = trim((string) ($this->request->getPost($campo) ?? ''));
        }

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderArmar('Sesion expirada, intente de nuevo.', array_merge($cargaIds, $cajaIds), $embarque);
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container()->get(SessionManager::class);

        try {
            // Por cada carga marcada entran sus bultos ya escaneados y libres; el resto queda en bodega.
            $lpnIds = array_merge($this->container()->get(CargasParaSalidaInterface::class)->lpnsListos($cargaIds), $cajaIds);

            $ordenId = $this->container()->get(CrearOrdenSalidaHandler::class)->handle(new CrearOrdenSalidaCommand(
                null,
                $lpnIds,
                null,
                (string) $sessionManager->currentUsuarioId(),
                [
                    'numeroContenedor' => $embarque['numero_contenedor'],
                    'numeroSello' => $embarque['numero_sello'],
                    'navieraId' => $embarque['naviera_id'],
                    'nombreBarco' => $embarque['nombre_barco'],
                    'fechaCargue' => $embarque['fecha_cargue'],
                    'fechaSalidaEtd' => $embarque['fecha_salida_etd'],
                    'destinoFinal' => $embarque['destino_final'],
                ],
            ));

            $_SESSION['salida_mensaje'] = 'Salida creada con ' . count($lpnIds) . ' bultos (' . count($cargaIds) . ' carga(s)' . ($cajaIds !== [] ? ' y ' . count($cajaIds) . ' caja(s) reempacada(s)' : '') . '). Bodega ya puede escanearlos al cargar el contenedor.';

            return redirect()->to('/salidas/' . $ordenId);
        } catch (ValidationException $e) {
            return $this->renderArmar($e->getMessage(), array_merge($cargaIds, $cajaIds), $embarque);
        }
    }

    public function show(string $id): string
    {
        $orden = $this->container()->get(VerOrdenSalidaHandler::class)->handle(new VerOrdenSalidaQuery($id));

        if ($orden === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        // Puede ser consolidada: los clientes salen de las cargas que lleva.
        $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
        $clienteNombre = implode(', ', $repositorio->clientesPorOrden([$orden->id()])[$orden->id()] ?? ['—']);

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
            'avance' => $repositorio->avancePorCarga($orden->id()),
            'navieras' => $navieras,
            'fotos' => $this->container()->get(FotoEvidenciaRepositoryInterface::class)->listar(TipoEvidencia::Salida, $orden->id()),
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
    /**
     * Reporte de salida (formato de la hoja de trafico): una fila por carga con fecha de ingreso,
     * compania, cliente, marca, bultos, p3, peso, valor, aduanero y descripcion. Se puede
     * filtrar por aduanero (?aduanero=) e imprimir, o bajar en Excel (reporteExcel).
     */
    public function reporte(string $id): string
    {
        $datos = $this->datosReporte($id);

        if ($datos === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        return view('salida/reporte_imprimir', $datos);
    }

    public function reporteExcel(string $id): ResponseInterface|string
    {
        $datos = $this->datosReporte($id);

        if ($datos === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        $filas = [['FECHA', 'COMPAÑÍA', 'CLIENTE', 'MARCA', 'BULTOS', 'CUBICAJE P3', 'PESO', 'VALOR', 'ADUANERO', 'DESCRIPCION']];
        $totales = ['bultos' => 0, 'pies3' => 0.0, 'kilos' => 0.0, 'valor' => 0.0];

        foreach ($datos['filas'] as $f) {
            $filas[] = [
                $f['fechaIngreso'] !== null ? (new \DateTimeImmutable($f['fechaIngreso']))->format('m/d/Y') : '',
                mb_strtoupper($f['compania']), mb_strtoupper($f['cliente']), mb_strtoupper($f['marca']),
                $f['bultos'], $f['pies3'], $f['kilos'], $f['valor'],
                mb_strtoupper($f['aduanero']), mb_strtoupper($f['descripcion']),
            ];

            foreach ($totales as $k => $_) {
                $totales[$k] += $f[$k];
            }
        }

        $filas[] = ['TOTALES', '', '', '', $totales['bultos'], round($totales['pies3'], 2), round($totales['kilos'], 2), round($totales['valor'], 2), '', ''];

        $orden = $datos['orden'];
        // "DO.2 / 2026" (+ aduanero) -> reporte-salida-do-2-2026-marara.xlsx
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($orden->etiquetaDo() . ' ' . $datos['aduanero'])), '-');
        $contenido = $this->container()->get(EscritorXlsx::class)->generar([
            $orden->numeroDo() => ['filas' => $filas, 'anchos' => [12, 24, 28, 18, 9, 13, 12, 13, 18, 28]],
        ]);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="reporte-salida-' . $slug . '.xlsx"')
            ->setBody($contenido);
    }

    /** @return array{orden: \Courier\Salida\Domain\SalidaOrden, filas: array<int, array<string, mixed>>, aduaneros: array<int, string>, aduanero: string}|null */
    private function datosReporte(string $id): ?array
    {
        $orden = $this->container()->get(VerOrdenSalidaHandler::class)->handle(new VerOrdenSalidaQuery($id));

        if ($orden === null) {
            return null;
        }

        $filas = $this->container()->get(SalidaOrdenRepositoryInterface::class)->manifiesto($orden->id());
        // Ordenado como la hoja de trafico: por aduanero y fecha de ingreso.
        usort($filas, static fn (array $a, array $b) => [$a['aduanero'], $a['fechaIngreso'], $a['compania']] <=> [$b['aduanero'], $b['fechaIngreso'], $b['compania']]);

        $aduaneros = array_values(array_unique(array_filter(array_column($filas, 'aduanero'))));
        $aduanero = trim((string) $this->request->getGet('aduanero'));

        if ($aduanero !== '') {
            $filas = array_values(array_filter($filas, static fn (array $f) => $f['aduanero'] === $aduanero));
        }

        return ['orden' => $orden, 'filas' => $filas, 'aduaneros' => $aduaneros, 'aduanero' => $aduanero];
    }

    public function manifiesto(string $id): string
    {
        $orden = $this->container()->get(VerOrdenSalidaHandler::class)->handle(new VerOrdenSalidaQuery($id));

        if ($orden === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        return view('salida/manifiesto_imprimir', [
            'orden' => $orden,
            // Titulo: el cliente, o los clientes si la salida es consolidada.
            'titulo' => implode(' / ', $this->container()->get(SalidaOrdenRepositoryInterface::class)->clientesPorOrden([$orden->id()])[$orden->id()] ?? []),
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
            // Mismo ajuste que la recepcion: el lector puede escribir el guion como apostrofo.
            $codigo = ConfirmarRecepcionLpnHandler::normalizarCodigo((string) ($this->request->getPost('codigo') ?? ''));
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
            // Una salida consolidada lleva cargas de varios clientes.
            'clientesPorOrden' => $this->container()->get(SalidaOrdenRepositoryInterface::class)->clientesPorOrden(array_map(static fn ($o) => $o->id(), $ordenes)),
            'clientes' => $clientes,
            'filtros' => ['estado' => $estado, 'cliente_id' => $clienteId],
        ]);
    }
}
