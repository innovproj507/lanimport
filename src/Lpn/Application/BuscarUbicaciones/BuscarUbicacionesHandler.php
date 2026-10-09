<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\BuscarUbicaciones;

use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class BuscarUbicacionesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly UbicacionRepositoryInterface $ubicaciones)
    {
    }

    public function handle(QueryInterface $query): BuscarUbicacionesResult
    {
        assert($query instanceof BuscarUbicacionesQuery);

        $resultado = $this->ubicaciones->searchPaginado($query->termino, $query->page, $query->perPage);

        return new BuscarUbicacionesResult($resultado['items'], $resultado['total'], $query->page, $query->perPage);
    }
}
