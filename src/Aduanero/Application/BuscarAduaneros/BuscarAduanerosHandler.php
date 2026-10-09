<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\BuscarAduaneros;

use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class BuscarAduanerosHandler implements QueryHandlerInterface
{
    public function __construct(private readonly AduaneroRepositoryInterface $aduaneros)
    {
    }

    public function handle(QueryInterface $query): BuscarAduanerosResult
    {
        assert($query instanceof BuscarAduanerosQuery);

        $resultado = $this->aduaneros->searchPaginado($query->termino, $query->page, $query->perPage);

        return new BuscarAduanerosResult($resultado['items'], $resultado['total'], $query->page, $query->perPage);
    }
}
