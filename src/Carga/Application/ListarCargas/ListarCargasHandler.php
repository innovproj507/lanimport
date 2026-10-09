<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ListarCargas;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ListarCargasHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CargaRepositoryInterface $cargas)
    {
    }

    /** @return array<int, \Courier\Carga\Domain\Carga> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarCargasQuery);

        return $this->cargas->findAll();
    }
}
