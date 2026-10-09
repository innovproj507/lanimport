<?php

declare(strict_types=1);

namespace Courier\Pais\Application\BuscarPaises;

use Courier\Pais\Domain\PaisRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class BuscarPaisesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PaisRepositoryInterface $paises)
    {
    }

    public function handle(QueryInterface $query): BuscarPaisesResult
    {
        assert($query instanceof BuscarPaisesQuery);

        $resultado = $this->paises->searchPaginado($query->termino, $query->page, $query->perPage);

        return new BuscarPaisesResult($resultado['items'], $resultado['total'], $query->page, $query->perPage);
    }
}
