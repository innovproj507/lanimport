<?php

declare(strict_types=1);

namespace Courier\Carga\Application\BuscarCargas;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class BuscarCargasHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CargaRepositoryInterface $cargas)
    {
    }

    public function handle(QueryInterface $query): BuscarCargasResult
    {
        assert($query instanceof BuscarCargasQuery);

        $resultado = $this->cargas->searchPaginado($query->termino, $query->estado, $query->page, $query->perPage);

        return new BuscarCargasResult($resultado['items'], $resultado['total'], $query->page, $query->perPage);
    }
}
