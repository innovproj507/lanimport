<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ListarLpnsPorCarga;

use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ListarLpnsPorCargaHandler implements QueryHandlerInterface
{
    public function __construct(private readonly LpnRepositoryInterface $lpns)
    {
    }

    /** @return array<int, \Courier\Lpn\Domain\Lpn> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarLpnsPorCargaQuery);

        return $this->lpns->findByCargaId($query->cargaId);
    }
}
