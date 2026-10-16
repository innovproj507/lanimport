<?php

declare(strict_types=1);

namespace Courier\Naviera\Application\BuscarNavieras;

use Courier\Naviera\Domain\NavieraRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class BuscarNavierasHandler implements QueryHandlerInterface
{
    public function __construct(private readonly NavieraRepositoryInterface $navieras)
    {
    }

    public function handle(QueryInterface $query): BuscarNavierasResult
    {
        assert($query instanceof BuscarNavierasQuery);

        $resultado = $this->navieras->searchPaginado($query->termino, $query->page, $query->perPage);

        return new BuscarNavierasResult($resultado['items'], $resultado['total'], $query->page, $query->perPage);
    }
}
