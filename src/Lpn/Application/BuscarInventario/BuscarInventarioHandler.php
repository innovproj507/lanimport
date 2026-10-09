<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\BuscarInventario;

use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class BuscarInventarioHandler implements QueryHandlerInterface
{
    public function __construct(private readonly LpnRepositoryInterface $lpns)
    {
    }

    /** @return array<int, \Courier\Lpn\Domain\Lpn> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof BuscarInventarioQuery);

        return $this->lpns->search([
            'estado' => $query->estado,
            'ubicacionId' => $query->ubicacionId,
            'clienteId' => $query->clienteId,
            'cargaCodigo' => $query->cargaCodigo,
            'aduaneroIdentificador' => $query->aduaneroIdentificador,
        ]);
    }
}
