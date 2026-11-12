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
use Courier\Carga\Domain\CargaRepositoryInterface;
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
use Courier\Salida\Application\AjustarBultosCargaSalida\AjustarBultosCargaSalidaCommand;
use Courier\Salida\Application\AjustarBultosCargaSalida\AjustarBultosCargaSalidaHandler;
use Courier\Salida\Application\DevolverCargaSalida\DevolverCargaSalidaCommand;
use Courier\Salida\Application\DevolverCargaSalida\DevolverCargaSalidaHandler;
use Courier\Salida\Application\MoverCargasSalida\MoverCargasSalidaCommand;
use Courier\Salida\Application\MoverCargasSalida\MoverCargasSalidaHandler;
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

        $cantidades = $this->request->getPost('cantidad');

        return view('salida/armar', [
            'cargas' => $this->container()->get(CargasParaSalidaInterface::class)->listar(),
            'cajas' => $this->container()->get(CargasParaSalidaInterface::class)->cajasListas(),
            'navieras' => $this->container()->get(ListarNavierasHandler::class)->handle(new ListarNavierasQuery(true)),
            'seleccionadas' => $seleccionadas,
            // "Bultos a subir" ya escritos (si hubo un error), por carga.
            'cantidades' => is_array($cantidades) ? array_map('intval', $cantidades) : [],
            // Etiquetas exactas ya elegidas por carga (si hubo un error), para no perderlas.
            'elegidas' => is_array($this->request->getPost('lpns')) ? $this->request->getPost('lpns') : [],
            'embarque' => $embarque,
            'ordenDestino' => $ordenDestino,
            'error' => $error,
        ]);
    }

    /**
     * Bultos que suben de cada carga marcada: trafico puede subir solo una parte ("Bultos a subir",
     * campo cantidad[cargaId]); entran los primeros N listos por consecutivo y el resto queda en
     * bodega para otro contenedor. Sin cantidad, entran todos los listos.
     *
     * @param array<int, string> $cargaIds
     * @return array<int, string> ids de LPN
     */
    private function lpnsDeCargasMarcadas(array $cargaIds): array
    {
        $listos = $this->container()->get(CargasParaSalidaInterface::class)->lpnsListosPorCarga($cargaIds);
        $cantidades = $this->request->getPost('cantidad');
        $cantidades = is_array($cantidades) ? $cantidades : [];
        // Etiquetas exactas que eligio trafico ("Elegir etiquetas"): lpns[cargaId][] = lpnId.
        $elegidas = $this->request->getPost('lpns');
        $elegidas = is_array($elegidas) ? $elegidas : [];
        $lpnIds = [];

        foreach ($cargaIds as $cargaId) {
            $disponibles = $listos[$cargaId] ?? [];

            if (!empty($elegidas[$cargaId]) && is_array($elegidas[$cargaId])) {
                $pedidas = array_values(array_unique(array_map('strval', $elegidas[$cargaId])));
                $noListas = array_diff($pedidas, $disponibles);

                if ($noListas !== []) {
                    $tracking = (string) ($this->container()->get(CargaRepositoryInterface::class)->findById($cargaId)?->trackingNumero() ?? 'la carga');

                    throw new ValidationException("De la carga {$tracking}, " . count($noListas) . ' de las etiquetas elegidas ya no estan disponibles (otra salida las tomo o cambiaron de estado). Vuelva a elegirlas.');
                }

                array_push($lpnIds, ...$pedidas);

                continue;
            }
            $pedida = isset($cantidades[$cargaId]) && trim((string) $cantidades[$cargaId]) !== '' ? (int) $cantidades[$cargaId] : count($disponibles);

            if ($pedida < 1 || $pedida > count($disponibles)) {
                $tracking = (string) ($this->container()->get(CargaRepositoryInterface::class)->findById($cargaId)?->trackingNumero() ?? 'la carga');

                throw new ValidationException(count($disponibles) === 0
                    ? "La carga {$tracking} ya no tiene bultos listos para salir. Recargue la pagina."
                    : "La carga {$tracking} tiene " . count($disponibles) . " bultos listos: indique entre 1 y " . count($disponibles) . " bultos a subir.");
            }

            array_push($lpnIds, ...array_slice($disponibles, 0, $pedida));
        }

        return $lpnIds;
    }

    /** JSON: bultos listos de una carga, para "Elegir etiquetas" en Armar salida / Agregar cargas. */
    public function lpnsListos(string $cargaId): ResponseInterface
    {
        return $this->response->setJSON([
            'lpns' => $this->container()->get(CargasParaSalidaInterface::class)->lpnsListosDetalle($cargaId),
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

        // A un DO no se le agregan cargas a mano: todo entra por una OS ("Agregar OS").
        if ($orden->tieneDo()) {
            $_SESSION['salida_error'] = "A {$orden->etiquetaDo()} no se le agregan cargas directamente: arme una OS en Orden de Salida y use \"Agregar OS\".";

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

        // A un DO no se le agregan cargas a mano: todo entra por una OS ("Agregar OS").
        if ($orden->tieneDo()) {
            $_SESSION['salida_error'] = "A {$orden->etiquetaDo()} no se le agregan cargas directamente: arme una OS en Orden de Salida y use \"Agregar OS\".";

            return redirect()->to('/salidas/' . $orden->id());
        }

        $cargaIds = $this->request->getPost('carga_ids') ?? [];
        $cargaIds = is_array($cargaIds) ? array_values(array_unique(array_map('strval', $cargaIds))) : [];
        $cajaIds = $this->request->getPost('caja_ids') ?? [];
        $cajaIds = is_array($cajaIds) ? array_values(array_unique(array_map('strval', $cajaIds))) : [];

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderArmar('Sesion expirada, intente de nuevo.', array_merge($cargaIds, $cajaIds), [], $orden);
        }

        try {
            $lpnIds = array_merge($this->lpnsDeCargasMarcadas($cargaIds), $cajaIds);
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
            // Por cada carga marcada entran sus bultos escaneados y libres (o solo los N que indico
            // trafico); el resto queda en bodega.
            $lpnIds = array_merge($this->lpnsDeCargasMarcadas($cargaIds), $cajaIds);
            // Toda salida nace como OS (solo cargas): sin DO ni contenedor. Trafico los pone despues
            // desde Orden de Salida ("Generar DO").
            $ordenId = $this->container()->get(CrearOrdenSalidaHandler::class)->handle(new CrearOrdenSalidaCommand(
                null,
                $lpnIds,
                null,
                (string) $sessionManager->currentUsuarioId(),
                null,
                false,
            ));

            $resumen = count($lpnIds) . ' bultos (' . count($cargaIds) . ' carga(s)' . ($cajaIds !== [] ? ' y ' . count($cajaIds) . ' caja(s) reempacada(s)' : '') . ')';
            $_SESSION['salida_mensaje'] = "OS creada con {$resumen}. Puede agregarle mas cargas; bodega ya puede escanearla.";

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
            'otrasSalidas' => $orden->estado()->value === 'pendiente' ? $this->otrasSalidas($orden) : [],
            // Cargas que salen en parte: que bultos se quedan (por carga), para que trafico lo vea.
            'quedanPorCarga' => array_column(
                $this->container()->get(SalidaOrdenRepositoryInterface::class)->detalleCargue($orden->id()),
                'quedan',
                'cargaId',
            ),
            'mensaje' => $mensaje,
            'error' => $error,
        ]);
    }

    public function guardarEmbarque(string $id): RedirectResponse
    {
        if (Csrf::verify($this->request->getPost('_csrf'))) {
            $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
            $teniaDo = $repositorio->findById($id)?->tieneDo() ?? true;

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
                    (string) $this->request->getPost('numero_do'),
                ));
                $_SESSION['salida_mensaje'] = $teniaDo
                    ? 'Datos del embarque guardados.'
                    : 'DO generado: ' . ($repositorio->findById($id)?->etiquetaDo() ?? '') . '. Inventario confirma la salida cuando el contenedor salga.';
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

    /**
     * "Orden de carga" que trafico imprime y entrega a bodega: el contenedor y, por carga, las
     * etiquetas exactas que deben subir (con su ubicacion), para que bodega las busque y escanee.
     */
    public function ordenCarga(string $id): string
    {
        $orden = $this->container()->get(VerOrdenSalidaHandler::class)->handle(new VerOrdenSalidaQuery($id));

        if ($orden === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        return view('salida/orden_carga_imprimir', [
            'orden' => $orden,
            'grupos' => $this->container()->get(SalidaOrdenRepositoryInterface::class)->detalleCargue($orden->id()),
            'empresa' => $this->container()->get(ConfiguracionRepositoryInterface::class)->datosEmpresa(),
            'usuario' => $this->container()->get(\Courier\Auth\Domain\UsuarioRepositoryInterface::class)->findById($orden->creadoPorUsuarioId())?->nombre(),
        ]);
    }

    /** Manifiesto en Excel: las mismas filas y totales que el impreso, mas los datos del embarque al final. */
    public function manifiestoExcel(string $id): ResponseInterface|string
    {
        $orden = $this->container()->get(VerOrdenSalidaHandler::class)->handle(new VerOrdenSalidaQuery($id));

        if ($orden === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
        $titulo = implode(' / ', $repositorio->aduanerosPorOrden([$orden->id()])[$orden->id()] ?? []);
        $embarque = $orden->embarque();

        $filas = [['NOMBRE', 'MARCA', 'COMPAÑÍA', 'ADUANERO', 'BULTOS', 'KILOS', 'CUB/P3', 'VALOR']];
        $totales = ['bultos' => 0, 'kilos' => 0.0, 'pies3' => 0.0, 'valor' => 0.0];

        foreach ($repositorio->manifiesto($orden->id()) as $f) {
            $filas[] = [
                mb_strtoupper($f['cliente']), mb_strtoupper($f['marca']), mb_strtoupper($f['compania']), mb_strtoupper($f['aduanero']),
                (int) $f['bultos'], round((float) $f['kilos'], 2), $f['pies3'] > 0 ? round((float) $f['pies3'], 2) : '', round((float) $f['valor'], 2),
            ];

            foreach ($totales as $k => $_) {
                $totales[$k] += $f[$k];
            }
        }

        $filas[] = ['TOTALES', '', '', '', (int) $totales['bultos'], round($totales['kilos'], 2), $totales['pies3'] > 0 ? round($totales['pies3'], 2) : '', round($totales['valor'], 2)];

        // Datos del embarque, debajo de la tabla (la primera fila del Excel es el encabezado fijo).
        $filas[] = [];
        $datos = [
            'DO' => $orden->etiquetaDo(),
            'ADUANERO' => mb_strtoupper($titulo),
            'FECHA' => ($embarque->fechaCargue ?? $orden->createdAt())->format('d/m/Y'),
            'CONTENEDOR' => $embarque->contenedorFormateado() ?? '',
            'SELLO' => $embarque->numeroSello ?? '',
            'NAVIERA' => $embarque->navieraNombre ?? '',
            'BARCO' => $embarque->nombreBarco ?? '',
            'ETD' => $embarque->fechaSalidaEtd?->format('d/m/Y') ?? '',
            'DESTINO FINAL' => mb_strtoupper($embarque->destinoFinal ?? ''),
        ];

        foreach ($datos as $campo => $valor) {
            if ($valor !== '') {
                $filas[] = [$campo, $valor];
            }
        }

        $contenido = $this->container()->get(EscritorXlsx::class)->generar([
            'Manifiesto' => ['filas' => $filas, 'anchos' => [34, 20, 24, 22, 10, 12, 11, 14]],
        ]);
        // "DO.261 / 2026" (+ aduanero) -> manifiesto-do-261-2026-alen.xlsx
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($orden->etiquetaDo() . ' ' . $titulo)), '-');

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="manifiesto-' . $slug . '.xlsx"')
            ->setBody($contenido);
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
            // Titulo: el aduanero, o los aduaneros si la salida lleva cargas de varios.
            'titulo' => implode(' / ', $this->container()->get(SalidaOrdenRepositoryInterface::class)->aduanerosPorOrden([$orden->id()])[$orden->id()] ?? []),
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
        } catch (NotFoundException|ValidationException $e) {
            // Se dice por que no se confirmo (p.ej. falta escanear o trafico aun no genera el DO).
            $_SESSION['salida_error'] = $e->getMessage();

            return redirect()->to('/salidas/' . $id);
        }
    }

    public function cancelar(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/salidas');
        }

        // Una OS (sin DO) vuelve a Orden de Salida; un DO, a Contenedor.
        $orden = $this->container()->get(SalidaOrdenRepositoryInterface::class)->findById($id);
        $volver = $orden !== null && $orden->tieneDo() ? '/salidas/historial' : '/salidas/ordenes';

        try {
            $this->container()->get(CancelarOrdenSalidaHandler::class)->handle(new CancelarOrdenSalidaCommand($id));
        } catch (NotFoundException|ValidationException) {
            // si la orden ya no puede cancelarse, simplemente se vuelve al listado
        }

        return redirect()->to($volver);
    }

    /** Une este DO a otro del mismo contenedor: pasan todas sus cargas (con lo escaneado) y este se cancela. */
    public function unir(string $id): RedirectResponse
    {
        $destinoId = trim((string) $this->request->getPost('destino_id'));

        return $this->moverCargas($id, $destinoId !== '' ? $destinoId : null, null, true);
    }

    /**
     * Historial: une varios DO pendientes en uno. Cada DO marcado pasa sus cargas (con lo escaneado y
     * las fotos) al DO que queda, igual que "Unir" en el detalle, y queda cancelado con la nota.
     */
    public function unificar(): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/salidas/historial');
        }

        $ids = array_values(array_unique(array_filter(array_map('trim', (array) $this->request->getPost('ordenes')))));
        $destinoId = trim((string) $this->request->getPost('destino_id'));

        if (count($ids) < 2 || !in_array($destinoId, $ids, true)) {
            $_SESSION['salida_historial_error'] = 'Marque al menos 2 DO pendientes y elija en cual quedan unidos.';

            return redirect()->to('/salidas/historial');
        }

        $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
        $destino = $repositorio->findById($destinoId);

        if ($destino === null) {
            $_SESSION['salida_historial_error'] = 'El DO elegido ya no existe.';

            return redirect()->to('/salidas/historial');
        }

        $usuarioId = (string) $this->container()->get(SessionManager::class)->currentUsuarioId();
        $unidos = [];
        $errores = [];

        foreach ($ids as $origenId) {
            if ($origenId === $destinoId) {
                continue;
            }

            $etiqueta = $repositorio->findById($origenId)?->etiquetaDo() ?? $origenId;

            try {
                $this->container()->get(MoverCargasSalidaHandler::class)->handle(
                    new MoverCargasSalidaCommand($origenId, $destinoId, null, $usuarioId),
                );
                $unidos[] = $etiqueta;
            } catch (NotFoundException|ValidationException|\Courier\Shared\Domain\Exception\DomainException $e) {
                $errores[] = "{$etiqueta}: {$e->getMessage()}";
            }
        }

        if ($unidos !== []) {
            $_SESSION['salida_mensaje'] = 'Se unieron ' . implode(', ', $unidos) . " a {$destino->etiquetaDo()}. Lo ya escaneado y las fotos se conservan.";
        }

        if ($errores !== []) {
            $_SESSION['salida_error'] = 'No se pudieron unir: ' . implode(' · ', $errores);
        }

        return redirect()->to('/salidas/' . $destinoId);
    }

    /** Pasa una carga de este DO a otro (o a un DO nuevo), por ejemplo si se unio por error. */
    public function moverCarga(string $id): RedirectResponse
    {
        $destino = trim((string) $this->request->getPost('destino_id'));
        $grupo = trim((string) $this->request->getPost('grupo'));

        return $this->moverCargas($id, $destino === 'nuevo' || $destino === '' ? null : $destino, [$grupo], false);
    }

    /** @param array<int, string>|null $grupos */
    private function moverCargas(string $id, ?string $destinoId, ?array $grupos, bool $unir): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/salidas/' . $id);
        }

        if ($unir && $destinoId === null) {
            $_SESSION['salida_error'] = 'Elija el DO al que se une esta salida.';

            return redirect()->to('/salidas/' . $id);
        }

        $etiquetaOrigen = $this->container()->get(SalidaOrdenRepositoryInterface::class)->findById($id)?->etiquetaDo();

        try {
            /** @var SalidaOrden $destino */
            $destino = $this->container()->get(MoverCargasSalidaHandler::class)->handle(new MoverCargasSalidaCommand(
                $id,
                $destinoId,
                $grupos,
                (string) $this->container()->get(SessionManager::class)->currentUsuarioId(),
            ));
        } catch (NotFoundException|ValidationException|\Courier\Shared\Domain\Exception\DomainException $e) {
            $_SESSION['salida_error'] = $e->getMessage();

            return redirect()->to('/salidas/' . $id);
        }

        $origen = $this->container()->get(SalidaOrdenRepositoryInterface::class)->findById($id);

        if ($unir || $origen === null || $origen->lineas() === []) {
            // El DO de origen quedo sin cargas (se elimino): se sigue en el destino.
            $_SESSION['salida_mensaje'] = ($unir ? 'DO unido' : 'Carga movida') . " a {$destino->etiquetaDo()}"
                . ($origen === null && $etiquetaOrigen !== null ? " ({$etiquetaOrigen} quedo vacio y se elimino)" : '') . '. Lo ya escaneado y las fotos se conservan.';

            return redirect()->to('/salidas/' . $destino->id());
        }

        $_SESSION['salida_mensaje'] = "Carga movida a {$destino->etiquetaDo()}. Lo ya escaneado se conserva.";

        return redirect()->to('/salidas/' . $id);
    }

    /** JSON: los bultos de una carga para "Ajustar bultos" dentro de este DO. */
    public function bultosCarga(string $id, string $cargaId): ResponseInterface
    {
        return $this->response->setJSON([
            'bultos' => $this->container()->get(CargasParaSalidaInterface::class)->bultosParaAjuste($id, $cargaId),
        ]);
    }

    /** Trafico deja en el DO solo los bultos marcados de una carga (p.ej. 20 de 143); el resto vuelve a bodega. */
    public function ajustarCarga(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/salidas/' . $id);
        }

        $lpns = $this->request->getPost('lpns');
        $lpns = is_array($lpns) ? array_values(array_map('strval', $lpns)) : [];
        $orden = $this->container()->get(SalidaOrdenRepositoryInterface::class)->findById($id);

        // En un DO solo se quitan bultos (vuelven a bodega): los que entran llegan por una OS.
        if ($orden !== null && $orden->tieneDo()) {
            $enElDo = array_map(static fn ($l) => $l->lpnId(), $orden->lineas());

            if (array_diff($lpns, $enElDo) !== []) {
                $_SESSION['salida_error'] = "A {$orden->etiquetaDo()} no se le agregan bultos a mano: arme una OS en Orden de Salida y use \"Agregar OS\".";

                return redirect()->to('/salidas/' . $id);
            }
        }

        try {
            $r = $this->container()->get(AjustarBultosCargaSalidaHandler::class)->handle(new AjustarBultosCargaSalidaCommand(
                $id,
                trim((string) $this->request->getPost('carga_id')),
                $lpns,
            ));
            $_SESSION['salida_mensaje'] = "Bultos ajustados: quedan {$r['quedan']} en " . ($orden?->tieneDo() ? 'este DO' : 'esta OS')
                . ($r['quitados'] > 0 ? ", {$r['quitados']} volvieron a bodega" : '')
                . ($r['agregados'] > 0 ? ", se agregaron {$r['agregados']}" : '') . '.';
        } catch (NotFoundException|ValidationException|\Courier\Shared\Domain\Exception\DomainException $e) {
            $_SESSION['salida_error'] = $e->getMessage();
        }

        return redirect()->to('/salidas/' . $id);
    }

    /** Saca una carga del DO y la devuelve a bodega (sus bultos vuelven a estar disponibles). */
    public function devolverCarga(string $id): RedirectResponse
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return redirect()->to('/salidas/' . $id);
        }

        try {
            $bultos = $this->container()->get(DevolverCargaSalidaHandler::class)->handle(new DevolverCargaSalidaCommand(
                $id,
                trim((string) $this->request->getPost('grupo')),
            ));
            $_SESSION['salida_mensaje'] = "Se devolvieron {$bultos} bultos a bodega: vuelven a estar disponibles para otra salida.";
        } catch (NotFoundException|ValidationException|\Courier\Shared\Domain\Exception\DomainException $e) {
            $_SESSION['salida_error'] = $e->getMessage();
        }

        if (isset($_SESSION['salida_mensaje']) && $this->container()->get(SalidaOrdenRepositoryInterface::class)->findById($id) === null) {
            $_SESSION['salida_mensaje'] .= ' El DO quedo vacio y se elimino.';

            return redirect()->to('/salidas/historial');
        }

        return redirect()->to('/salidas/' . $id);
    }

    /**
     * Otros DO a los que se puede unir este o mover una carga: pendientes, y los confirmados
     * mas recientes (por si bodega finalizo el contenedor antes de terminar de cargarlo).
     *
     * @return array<int, array{id: string, etiqueta: string, estado: string, contenedor: ?string, clientes: string}>
     */
    private function otrasSalidas(SalidaOrden $orden): array
    {
        $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
        $ordenes = array_merge(
            $repositorio->search(['estado' => 'pendiente']),
            array_slice($repositorio->search(['estado' => 'confirmada']), 0, 20),
        );
        $ordenes = array_values(array_filter($ordenes, static fn (SalidaOrden $o) => $o->id() !== $orden->id()));
        $clientes = $repositorio->clientesPorOrden(array_map(static fn (SalidaOrden $o) => $o->id(), $ordenes));

        return array_map(static function (SalidaOrden $o) use ($clientes): array {
            $nombres = $clientes[$o->id()] ?? [];

            return [
                'id' => $o->id(),
                'etiqueta' => $o->etiquetaDo(),
                'estado' => $o->estado()->value,
                'contenedor' => $o->embarque()->contenedorFormateado(),
                // Corto para el <select>: con 19 clientes la opcion pasaba de 300 letras y la lista
                // del navegador salia en blanco. La lista completa va en 'clientesTodos' (title).
                'clientes' => implode(', ', array_slice($nombres, 0, 2)) . (count($nombres) > 2 ? ' +' . (count($nombres) - 2) . ' mas' : ''),
                'clientesTodos' => implode(', ', $nombres),
            ];
        }, $ordenes);
    }

    /** Agregar OS a un DO que ya existe (trafico): lista de OS pendientes para marcar. */
    public function agregarOsForm(string $id): RedirectResponse|string
    {
        $destino = $this->container()->get(SalidaOrdenRepositoryInterface::class)->findById($id);

        if ($destino === null) {
            $this->response->setStatusCode(404);

            return 'Orden de salida no encontrada.';
        }

        if (!$destino->tieneDo() || $destino->estado()->value === 'cancelada') {
            return redirect()->to('/salidas/' . $id);
        }

        return $this->renderAgregarOs($destino, null, []);
    }

    /**
     * Pasa las OS marcadas al DO: sus cargas entran con lo que bodega ya escaneo y sus fotos,
     * y cada OS queda vacia y se elimina (MoverCargasSalidaHandler, igual que unir).
     */
    public function agregarOs(string $id): RedirectResponse|string
    {
        $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
        $destino = $repositorio->findById($id);

        if ($destino === null || !$destino->tieneDo()) {
            return redirect()->to('/salidas/' . $id);
        }

        $osIds = array_values(array_unique(array_filter(array_map('trim', (array) $this->request->getPost('os_ids')))));

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderAgregarOs($destino, 'Sesion expirada, intente de nuevo.', $osIds);
        }

        if ($osIds === []) {
            return $this->renderAgregarOs($destino, 'Marque al menos una OS.', $osIds);
        }

        $usuarioId = (string) $this->container()->get(SessionManager::class)->currentUsuarioId();
        $agregadas = [];
        $errores = [];

        foreach ($osIds as $osId) {
            $os = $repositorio->findById($osId);

            if ($os === null || $os->tieneDo() || $os->estado()->value !== 'pendiente') {
                $errores[] = ($os?->etiquetaDo() ?? 'OS') . ': ya no es una OS pendiente.';
                continue;
            }

            try {
                $this->container()->get(MoverCargasSalidaHandler::class)->handle(
                    new MoverCargasSalidaCommand($os->id(), $destino->id(), null, $usuarioId),
                );
                $agregadas[] = $os->etiquetaDo();
            } catch (NotFoundException|ValidationException|\Courier\Shared\Domain\Exception\DomainException $e) {
                $errores[] = "{$os->etiquetaDo()}: {$e->getMessage()}";
            }
        }

        if ($agregadas !== []) {
            $_SESSION['salida_mensaje'] = 'Se agregaron ' . implode(', ', $agregadas) . " a {$destino->etiquetaDo()}. Lo ya escaneado y las fotos se conservan.";
        }

        if ($errores !== []) {
            $_SESSION['salida_error'] = 'No se pudieron agregar: ' . implode(' · ', $errores);
        }

        return redirect()->to('/salidas/' . $destino->id());
    }

    /** @param array<int, string> $seleccionadas */
    private function renderAgregarOs(SalidaOrden $destino, ?string $error, array $seleccionadas): string
    {
        if ($error !== null) {
            $this->response->setStatusCode(422);
        }

        $ordenes = array_values(array_filter(
            $this->container()->get(ListarOrdenesSalidaHandler::class)->handle(new ListarOrdenesSalidaQuery('pendiente', null)),
            static fn ($o) => !$o->tieneDo(),
        ));
        usort($ordenes, static fn ($a, $b) => $a->createdAt() <=> $b->createdAt());
        $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
        $ids = array_map(static fn ($o) => $o->id(), $ordenes);

        return view('salida/agregar_os', [
            'destino' => $destino,
            'ordenes' => $ordenes,
            'clientesPorOrden' => $repositorio->clientesPorOrden($ids),
            'aduanerosPorOrden' => $repositorio->aduanerosPorOrden($ids),
            'seleccionadas' => $seleccionadas,
            'error' => $error,
        ]);
    }

    /**
     * Crear DO (Salidas, trafico): datos del contenedor + las OS que suben a el. La primera OS
     * marcada recibe el DO y el embarque; las demas se le unen (con lo escaneado y sus fotos).
     */
    public function crearDoForm(): string
    {
        $os = trim((string) ($this->request->getGet('os') ?? ''));

        return $this->renderCrearDo(null, $os !== '' ? [$os] : [], []);
    }

    public function crearDo(): RedirectResponse|string
    {
        $osIds = array_values(array_unique(array_filter(array_map('trim', (array) $this->request->getPost('os_ids')))));
        $embarque = [];

        foreach (['numero_do', 'numero_contenedor', 'numero_sello', 'naviera_id', 'nombre_barco', 'fecha_cargue', 'fecha_salida_etd', 'destino_final'] as $campo) {
            $embarque[$campo] = trim((string) ($this->request->getPost($campo) ?? ''));
        }

        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderCrearDo('Sesion expirada, intente de nuevo.', $osIds, $embarque);
        }

        if ($osIds === []) {
            return $this->renderCrearDo('Marque al menos una OS que sube a este contenedor.', $osIds, $embarque);
        }

        $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
        $destino = $repositorio->findById($osIds[0]);

        if ($destino === null || $destino->tieneDo() || $destino->estado()->value !== 'pendiente') {
            return $this->renderCrearDo('La OS elegida ya no esta pendiente de DO. Recargue la pagina.', $osIds, $embarque);
        }

        try {
            // La primera OS recibe el DO y el contenedor (valida contenedor y numero de DO).
            $this->container()->get(ActualizarEmbarqueSalidaHandler::class)->handle(new ActualizarEmbarqueSalidaCommand(
                $destino->id(),
                $embarque['numero_contenedor'],
                $embarque['numero_sello'],
                $embarque['naviera_id'],
                $embarque['nombre_barco'],
                $embarque['fecha_cargue'],
                $embarque['fecha_salida_etd'],
                $embarque['destino_final'],
                $embarque['numero_do'],
            ));
        } catch (ValidationException|NotFoundException $e) {
            return $this->renderCrearDo($e->getMessage(), $osIds, $embarque);
        }

        // Las demas OS del mismo contenedor se unen a ese DO.
        $usuarioId = (string) $this->container()->get(SessionManager::class)->currentUsuarioId();
        $errores = [];

        foreach (array_slice($osIds, 1) as $origenId) {
            $etiqueta = $repositorio->findById($origenId)?->etiquetaDo() ?? $origenId;

            try {
                $this->container()->get(MoverCargasSalidaHandler::class)->handle(
                    new MoverCargasSalidaCommand($origenId, $destino->id(), null, $usuarioId),
                );
            } catch (NotFoundException|ValidationException|\Courier\Shared\Domain\Exception\DomainException $e) {
                $errores[] = "{$etiqueta}: {$e->getMessage()}";
            }
        }

        $do = $repositorio->findById($destino->id());
        $_SESSION['salida_mensaje'] = 'DO creado: ' . ($do?->etiquetaDo() ?? '') . ' con ' . count($osIds) . ' OS. Inventario confirma la salida cuando el contenedor salga.';

        if ($errores !== []) {
            $_SESSION['salida_error'] = 'No se pudieron unir: ' . implode(' · ', $errores);
        }

        return redirect()->to('/salidas/' . $destino->id());
    }

    /**
     * @param array<int, string> $seleccionadas OS marcadas
     * @param array<string, string> $embarque datos ya escritos (si hubo un error)
     */
    private function renderCrearDo(?string $error, array $seleccionadas, array $embarque): string
    {
        if ($error !== null) {
            $this->response->setStatusCode(422);
        }

        $ordenes = array_values(array_filter(
            $this->container()->get(ListarOrdenesSalidaHandler::class)->handle(new ListarOrdenesSalidaQuery('pendiente', null)),
            static fn ($o) => !$o->tieneDo(),
        ));
        usort($ordenes, static fn ($a, $b) => $a->createdAt() <=> $b->createdAt());
        $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
        $ids = array_map(static fn ($o) => $o->id(), $ordenes);

        return view('salida/crear_do', [
            'ordenes' => $ordenes,
            'clientesPorOrden' => $repositorio->clientesPorOrden($ids),
            'aduanerosPorOrden' => $repositorio->aduanerosPorOrden($ids),
            'navieras' => $this->container()->get(ListarNavierasHandler::class)->handle(new ListarNavierasQuery(true)),
            'seleccionadas' => $seleccionadas,
            'embarque' => $embarque,
            'error' => $error,
        ]);
    }

    /**
     * Orden de Salida (menu propio): las OS que armo inventario y aun no tienen DO. Bodega ya las
     * puede escanear; trafico las abre para generar el DO y entonces pasan a Salidas.
     */
    public function ordenes(): string
    {
        $ordenes = array_values(array_filter(
            $this->container()->get(ListarOrdenesSalidaHandler::class)->handle(new ListarOrdenesSalidaQuery('pendiente', null)),
            static fn ($o) => !$o->tieneDo(),
        ));

        $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
        $ids = array_map(static fn ($o) => $o->id(), $ordenes);

        return view('salida/ordenes', [
            'ordenes' => $ordenes,
            'clientesPorOrden' => $repositorio->clientesPorOrden($ids),
            'aduanerosPorOrden' => $repositorio->aduanerosPorOrden($ids),
        ]);
    }

    public function historial(): string
    {
        $estado = (string) ($this->request->getGet('estado') ?? '');
        $clienteId = (string) ($this->request->getGet('cliente_id') ?? '');

        $ordenes = $this->container()->get(ListarOrdenesSalidaHandler::class)->handle(new ListarOrdenesSalidaQuery(
            $estado !== '' ? $estado : null,
            $clienteId !== '' ? $clienteId : null,
        ));

        // Salidas = las que ya tienen DO. Las OS pendientes sin DO estan en "Orden de Salida" (ordenes()).
        $ordenes = array_values(array_filter(
            $ordenes,
            static fn ($o) => $o->tieneDo() || $o->estado()->value !== 'pendiente',
        ));

        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());
        $mapaClientes = [];

        foreach ($clientes as $cliente) {
            $mapaClientes[$cliente->id()] = $cliente->nombreCompleto();
        }

        $repositorio = $this->container()->get(SalidaOrdenRepositoryInterface::class);
        $ids = array_map(static fn ($o) => $o->id(), $ordenes);

        $errorUnificar = $_SESSION['salida_historial_error'] ?? null;
        unset($_SESSION['salida_historial_error']);

        return view('salida/historial', [
            'errorUnificar' => $errorUnificar,
            'ordenes' => $ordenes,
            'mapaClientes' => $mapaClientes,
            // Una salida consolidada lleva cargas de varios clientes y aduaneros.
            'clientesPorOrden' => $repositorio->clientesPorOrden($ids),
            'aduanerosPorOrden' => $repositorio->aduanerosPorOrden($ids),
            'clientes' => $clientes,
            'filtros' => ['estado' => $estado, 'cliente_id' => $clienteId],
        ]);
    }
}
