<?php

declare(strict_types=1);

namespace App\Controllers;

use Courier\Auth\Infrastructure\Security\SessionManager;
use Courier\Carga\Application\ListarCargasPorCliente\ListarCargasPorClienteHandler;
use Courier\Carga\Application\ListarCargasPorCliente\ListarCargasPorClienteQuery;
use Courier\Cliente\Domain\ClienteRepositoryInterface;

final class PortalController extends BaseController
{
    public function index(): string
    {
        $usuarioId = (string) $this->container()->get(SessionManager::class)->currentUsuarioId();
        $cliente = $this->container()->get(ClienteRepositoryInterface::class)->findByUsuarioId($usuarioId);

        if ($cliente === null) {
            return view('portal/index', ['cliente' => null, 'cargas' => []]);
        }

        $cargas = $this->container()->get(ListarCargasPorClienteHandler::class)
            ->handle(new ListarCargasPorClienteQuery($cliente->id()));

        return view('portal/index', ['cliente' => $cliente, 'cargas' => $cargas]);
    }
}
