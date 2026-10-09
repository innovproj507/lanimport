<?php

declare(strict_types=1);

namespace App\Controllers;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Cliente\Application\ListarClientes\ListarClientesHandler;
use Courier\Cliente\Application\ListarClientes\ListarClientesQuery;
use Courier\Lpn\Application\BuscarInventario\BuscarInventarioHandler;
use Courier\Lpn\Application\BuscarInventario\BuscarInventarioQuery;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesHandler;
use Courier\Lpn\Application\ListarUbicaciones\ListarUbicacionesQuery;

final class InventarioController extends BaseController
{
    public function index(): string
    {
        $estado = (string) ($this->request->getGet('estado') ?? '');
        $ubicacionId = (string) ($this->request->getGet('ubicacion_id') ?? '');
        $clienteId = (string) ($this->request->getGet('cliente_id') ?? '');
        $cargaCodigo = (string) ($this->request->getGet('carga_codigo') ?? '');

        $query = new BuscarInventarioQuery(
            $estado !== '' ? $estado : null,
            $ubicacionId !== '' ? $ubicacionId : null,
            $clienteId !== '' ? $clienteId : null,
            $cargaCodigo !== '' ? $cargaCodigo : null,
        );

        $lpns = $this->container()->get(BuscarInventarioHandler::class)->handle($query);
        $ubicaciones = $this->container()->get(ListarUbicacionesHandler::class)->handle(new ListarUbicacionesQuery());
        $clientes = $this->container()->get(ListarClientesHandler::class)->handle(new ListarClientesQuery());

        $cargaRepository = $this->container()->get(CargaRepositoryInterface::class);
        $cargasPorId = [];

        foreach ($lpns as $lpn) {
            if ($lpn->cargaId() !== null && !isset($cargasPorId[$lpn->cargaId()])) {
                $cargasPorId[$lpn->cargaId()] = $cargaRepository->findById($lpn->cargaId());
            }
        }

        return view('inventario/index', [
            'lpns' => $lpns,
            'ubicaciones' => $ubicaciones,
            'clientes' => $clientes,
            'cargasPorId' => $cargasPorId,
            'filtros' => [
                'estado' => $estado,
                'ubicacion_id' => $ubicacionId,
                'cliente_id' => $clienteId,
                'carga_codigo' => $cargaCodigo,
            ],
        ]);
    }
}
