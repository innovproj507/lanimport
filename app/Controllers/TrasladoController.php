<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Csrf;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Courier\Bodega\Domain\BodegaRepositoryInterface;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnHandler;
use Courier\Salida\Domain\CargasParaSalidaInterface;
use Courier\Shared\Domain\BodegaActivaInterface;
use Courier\Shared\Domain\Exception\NotFoundException;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Traslado\Application\CrearTraslado\CrearTrasladoCommand;
use Courier\Traslado\Application\CrearTraslado\CrearTrasladoHandler;
use Courier\Traslado\Application\MoverTraslado\MoverTrasladoCommand;
use Courier\Traslado\Application\MoverTraslado\MoverTrasladoHandler;
use Courier\Traslado\Domain\TrasladoRepositoryInterface;

/**
 * Traslados entre bodegas: Inventario/Trafico arman el traslado (cargas de la bodega activa),
 * la bodega origen escanea y despacha, la destino escanea al llegar.
 */
final class TrasladoController extends BaseController
{
    public function index(): string
    {
        $repo = $this->container()->get(TrasladoRepositoryInterface::class);
        $estado = (string) ($this->request->getGet('estado') ?? 'abiertos');
        $estados = match ($estado) {
            'abiertos' => ['pendiente', 'en_camino'],
            'todos' => [],
            default => [$estado],
        };

        return view('traslados/index', [
            'traslados' => $repo->listar($estados),
            'estado' => $estado,
            'aviso' => $this->tomar('traslado_aviso'),
        ]);
    }

    public function nuevo(): string
    {
        return $this->renderNuevo(null);
    }

    public function store(): RedirectResponse|string
    {
        if (!Csrf::verify($this->request->getPost('_csrf'))) {
            return $this->renderNuevo('Sesion expirada, por favor intente de nuevo.');
        }

        try {
            $origen = $this->container()->get(BodegaActivaInterface::class)->id()
                ?? throw new ValidationException('Elija arriba la bodega de donde salen los bultos.');

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

            $id = $this->container()->get(CrearTrasladoHandler::class)->handle(new CrearTrasladoCommand(
                $origen,
                (string) $this->request->getPost('destino_bodega_id'),
                $lpnIds,
                (string) ($_SESSION['usuario_id'] ?? ''),
                (string) $this->request->getPost('nota'),
            ));
        } catch (ValidationException $e) {
            return $this->renderNuevo($e->getMessage());
        }

        $_SESSION['traslado_aviso'] = ['tipo' => 'ok', 'texto' => 'Traslado creado. La bodega origen lo escanea al cargarlo y lo despacha.'];

        return redirect()->to('/traslados/' . $id);
    }

    public function show(string $id): string
    {
        $repo = $this->container()->get(TrasladoRepositoryInterface::class);
        $traslado = $repo->findById($id);
        $fija = $this->container()->get(BodegaActivaInterface::class)->fija();

        if ($traslado === null || ($fija !== null && !in_array($fija, [$traslado->origenBodegaId(), $traslado->destinoBodegaId()], true))) {
            $this->response->setStatusCode(404);

            return 'Traslado no encontrado.';
        }

        return view('traslados/detalle', [
            'traslado' => $traslado,
            'lineas' => $repo->detalleLineas($traslado->id()),
            'fija' => $fija,
            'aviso' => $this->tomar('traslado_aviso'),
        ]);
    }

    /** POST traslados/{id}/{escanear|despachar|cancelar}. Con Accept: application/json responde JSON. */
    public function accion(string $id, string $accion): RedirectResponse|ResponseInterface
    {
        $json = str_contains((string) $this->request->getHeaderLine('Accept'), 'application/json');

        if (!Csrf::verify($this->request->getPost('_csrf') ?? $this->request->getHeaderLine('X-CSRF-Token'))) {
            return $this->responder($json, $id, 'error', 'Sesion expirada, recargue la pagina.');
        }

        try {
            $resultado = $this->container()->get(MoverTrasladoHandler::class)->handle(new MoverTrasladoCommand(
                $id,
                $accion,
                (string) ($_SESSION['usuario_id'] ?? ''),
                ConfirmarRecepcionLpnHandler::normalizarCodigo((string) ($this->request->getPost('codigo') ?? '')),
            ));
        } catch (ValidationException|NotFoundException $e) {
            return $this->responder($json, $id, 'error', $e->getMessage());
        }

        if ($json) {
            return $this->response->setJSON(['success' => true, 'data' => $resultado]);
        }

        return $this->responder(false, $id, 'ok', $resultado['mensaje'] ?? ($accion === 'escanear' ? 'Escaneado.' : 'Listo.'));
    }

    private function responder(bool $json, string $id, string $tipo, string $texto): RedirectResponse|ResponseInterface
    {
        if ($json) {
            return $this->response->setStatusCode($tipo === 'ok' ? 200 : 422)->setJSON(['success' => $tipo === 'ok', 'message' => $texto]);
        }

        $_SESSION['traslado_aviso'] = ['tipo' => $tipo, 'texto' => $texto];

        return redirect()->to('/traslados/' . $id);
    }

    private function renderNuevo(?string $error): string
    {
        if ($error !== null) {
            $this->response->setStatusCode(422);
        }

        $activa = $this->container()->get(BodegaActivaInterface::class)->id();
        $cargas = $activa !== null ? $this->container()->get(CargasParaSalidaInterface::class)->listar() : [];

        return view('traslados/nuevo', [
            'cargas' => array_values(array_filter($cargas, static fn (array $c) => $c['listos'] > 0)),
            'origen' => $activa,
            'destinos' => array_values(array_filter(
                $this->container()->get(BodegaRepositoryInterface::class)->findAllActivas(),
                static fn ($b) => $b->id() !== $activa,
            )),
            'error' => $error,
        ]);
    }

    /** @return array{tipo: string, texto: string}|null */
    private function tomar(string $clave): ?array
    {
        $valor = $_SESSION[$clave] ?? null;
        unset($_SESSION[$clave]);

        return $valor;
    }
}
