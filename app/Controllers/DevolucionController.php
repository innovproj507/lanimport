<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Bodega\Domain\BodegaRepositoryInterface;
use Courier\Devolucion\Application\AdjuntarDocumentoDevolucion\AdjuntarDocumentoDevolucionHandler;
use Courier\Devolucion\Application\CrearOrdenDevolucion\CrearOrdenDevolucionCommand;
use Courier\Devolucion\Application\CrearOrdenDevolucion\CrearOrdenDevolucionHandler;
use Courier\Devolucion\Application\MoverOrdenDevolucion\MoverOrdenDevolucionCommand;
use Courier\Devolucion\Application\MoverOrdenDevolucion\MoverOrdenDevolucionHandler;
use Courier\Devolucion\Domain\DatosTransporte;
use Courier\Devolucion\Domain\OrdenDevolucionRepositoryInterface;
use Courier\Evidencia\Domain\FotoEvidenciaRepositoryInterface;
use Courier\Evidencia\Domain\TipoEvidencia;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnHandler;
use Courier\Salida\Domain\CargasParaSalidaInterface;
use Courier\Shared\Domain\BodegaActivaInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Ordenes de devolucion al proveedor. Inventario/Trafico la arman con cargas de la bodega (de
 * cualquier cliente y proveedor); bodega escanea cada bulto al subirlo al camion y toma fotos por
 * carga; se llenan los datos del camion, la nota y los documentos; confirma la salida del camion
 * quien confirma las salidas (inventario/gerencia/owner), con todo escaneado.
 */
final class DevolucionController extends BaseController
{
    public function index(): string
    {
        $estado = (string) ($this->request->getGet('estado') ?? 'pendiente');
        $estados = match ($estado) {
            'todas' => [],
            'despachada', 'cancelada' => [$estado],
            default => ['pendiente'],
        };
        $estado = $estados === [] ? 'todas' : $estados[0];

        return view('devoluciones/index', [
            'ordenes' => $this->container()->get(OrdenDevolucionRepositoryInterface::class)
                ->listar($estados, $this->container()->get(BodegaActivaInterface::class)->id()),
            'estado' => $estado,
            'aviso' => $this->tomarAviso(),
        ]);
    }

    public function nueva(): string
    {
        return $this->renderNueva(null);
    }

    public function store(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderNueva('Sesion expirada, por favor intente de nuevo.');
        }

        try {
            $bodega = $this->bodegaDeLaOrden() ?? throw new ValidationException('Elija arriba la bodega de donde salen los bultos.');

            $cargaIds = array_values(array_unique(array_map('strval', (array) ($this->request->getPost('carga_ids') ?? []))));
            if ($cargaIds === []) {
                throw new ValidationException('Marque al menos una carga.');
            }

            // Como en Crear OS: de cada carga marcada van sus bultos disponibles, o los N indicados.
            $listos = $this->container()->get(CargasParaSalidaInterface::class)->lpnsListosPorCarga($cargaIds);
            $cantidades = (array) ($this->request->getPost('cantidad') ?? []);
            $lpnIds = [];

            foreach ($cargaIds as $cargaId) {
                $disponibles = $listos[$cargaId] ?? [];
                $pedida = trim((string) ($cantidades[$cargaId] ?? '')) !== '' ? (int) $cantidades[$cargaId] : count($disponibles);

                if ($disponibles === [] || $pedida < 1 || $pedida > count($disponibles)) {
                    throw new ValidationException('Revise la cantidad de bultos de cada carga (entre 1 y los disponibles).');
                }

                array_push($lpnIds, ...array_slice($disponibles, 0, $pedida));
            }

            $id = $this->container()->get(CrearOrdenDevolucionHandler::class)->handle(new CrearOrdenDevolucionCommand(
                $bodega,
                $lpnIds,
                (string) $this->request->getPost('motivo'),
                (string) $this->request->getPost('nota'),
                $this->transporteDesdeRequest(),
                (string) ($_SESSION['usuario_id'] ?? ''),
            ));
        } catch (ValidationException $e) {
            return $this->renderNueva($e->getMessage());
        }

        $_SESSION['devolucion_aviso'] = ['tipo' => 'ok', 'texto' => 'Orden de devolucion creada. Bodega escanea cada bulto al subirlo al camion y toma las fotos; luego se confirma la salida.'];

        return redirect()->to('/devoluciones/' . $id);
    }

    public function show(string $id): string
    {
        $repo = $this->container()->get(OrdenDevolucionRepositoryInterface::class);
        $orden = $repo->findById($id);
        $fija = $this->container()->get(BodegaActivaInterface::class)->fija();

        if ($orden === null || ($fija !== null && $orden->bodegaId() !== null && $orden->bodegaId() !== $fija)) {
            $this->response->setStatusCode(404);

            return 'Orden de devolucion no encontrada.';
        }

        $fotos = $this->container()->get(FotoEvidenciaRepositoryInterface::class)->listar(TipoEvidencia::Devolucion, $orden->id());

        return view('devoluciones/detalle', [
            'orden' => $orden,
            'lineas' => $repo->detalleLineas($orden->id()),
            'documentos' => $repo->documentos($orden->id()),
            'fotos' => $fotos,
            'aviso' => $this->tomarAviso(),
        ]);
    }

    /** POST devoluciones/{id}/{escanear|datos|quitar|cancelar|confirmar}. Con Accept: application/json responde JSON. */
    public function accion(string $id, string $accion): RedirectResponse|ResponseInterface
    {
        $json = str_contains((string) $this->request->getHeaderLine('Accept'), 'application/json');

        if (!Csrf::verify($this->request->getPost('_csrf') ?? $this->request->getHeaderLine('X-CSRF-Token'))) {
            return $this->responder($json, $id, 'error', 'Sesion expirada, recargue la pagina.');
        }

        try {
            $resultado = $this->container()->get(MoverOrdenDevolucionHandler::class)->handle(new MoverOrdenDevolucionCommand(
                $id,
                $accion,
                (string) ($_SESSION['usuario_id'] ?? ''),
                ConfirmarRecepcionLpnHandler::normalizarCodigo((string) ($this->request->getPost('codigo') ?? '')),
                (string) ($this->request->getPost('carga_id') ?? ''),
                $accion === MoverOrdenDevolucionCommand::DATOS ? $this->transporteDesdeRequest() : null,
                $accion === MoverOrdenDevolucionCommand::DATOS ? (string) $this->request->getPost('nota') : null,
                $accion === MoverOrdenDevolucionCommand::DATOS ? (string) $this->request->getPost('motivo') : null,
            ));
        } catch (ValidationException|NotFoundException $e) {
            return $this->responder($json, $id, 'error', $e->getMessage());
        }

        if ($json) {
            return $this->response->setJSON(['success' => true, 'data' => $resultado]);
        }

        $texto = $resultado['mensaje'] ?? ($accion === 'escanear' ? 'Escaneado: ' . $resultado['progreso']['escaneados'] . ' de ' . $resultado['progreso']['total'] . '.' : 'Listo.');

        return $this->responder(false, $id, 'ok', $texto);
    }

    /** Documentos de la orden (factura, nota de credito, guia...): abierta o ya despachada. */
    public function documentos(string $id): RedirectResponse|ResponseInterface
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->responder(false, $id, 'error', 'Sesion expirada, recargue la pagina.');
        }

        try {
            $n = $this->container()->get(AdjuntarDocumentoDevolucionHandler::class)
                ->handle($id, $this->archivosSubidos('documentos'), $_SESSION['usuario_id'] ?? null);
        } catch (ValidationException|NotFoundException $e) {
            return $this->responder(false, $id, 'error', $e->getMessage());
        }

        return $this->responder(false, $id, 'ok', "{$n} documento(s) adjuntos.");
    }

    private function responder(bool $json, string $id, string $tipo, string $texto): RedirectResponse|ResponseInterface
    {
        if ($json) {
            return $this->response->setStatusCode($tipo === 'ok' ? 200 : 422)->setJSON(['success' => $tipo === 'ok', 'message' => $texto]);
        }

        $_SESSION['devolucion_aviso'] = ['tipo' => $tipo, 'texto' => $texto];

        return redirect()->to('/devoluciones/' . $id);
    }

    private function renderNueva(?string $error): string
    {
        if ($error !== null) {
            $this->response->setStatusCode(422);
        }

        $bodega = $this->bodegaDeLaOrden();
        $cargas = $bodega !== null ? $this->container()->get(CargasParaSalidaInterface::class)->listar() : [];

        return view('devoluciones/nueva', [
            'cargas' => array_values(array_filter($cargas, static fn (array $c) => $c['listos'] > 0)),
            'bodega' => $bodega,
            'error' => $error,
        ]);
    }

    /** Bodega de donde sale la orden; null si hay varias y el usuario esta viendo "Todas". */
    private function bodegaDeLaOrden(): ?string
    {
        $varias = count($this->container()->get(BodegaRepositoryInterface::class)->findAllActivas()) > 1;
        $activa = $this->container()->get(BodegaActivaInterface::class);

        if ($varias && $activa->id() === null) {
            return null;
        }

        return $this->bodegaParaOperar();
    }

    private function transporteDesdeRequest(): DatosTransporte
    {
        return DatosTransporte::desde(
            (string) $this->request->getPost('transporte_empresa'),
            (string) $this->request->getPost('chofer'),
            (string) $this->request->getPost('placa'),
            (string) $this->request->getPost('cedula_chofer'),
        );
    }

    /**
     * Archivos de un input multiple (name="campo[]"), sin los vacios.
     *
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private function archivosSubidos(string $campo): array
    {
        $f = $_FILES[$campo] ?? null;
        if (!is_array($f) || !is_array($f['name'] ?? null)) {
            return [];
        }

        $archivos = [];
        foreach ($f['name'] as $i => $nombre) {
            if ((int) $f['error'][$i] === UPLOAD_ERR_NO_FILE || (string) $nombre === '') {
                continue;
            }
            $archivos[] = [
                'name' => (string) $nombre,
                'type' => (string) $f['type'][$i],
                'tmp_name' => (string) $f['tmp_name'][$i],
                'error' => (int) $f['error'][$i],
                'size' => (int) $f['size'][$i],
            ];
        }

        return $archivos;
    }

    /** @return array{tipo: string, texto: string}|null */
    private function tomarAviso(): ?array
    {
        $aviso = $_SESSION['devolucion_aviso'] ?? null;
        unset($_SESSION['devolucion_aviso']);

        return $aviso;
    }
}
