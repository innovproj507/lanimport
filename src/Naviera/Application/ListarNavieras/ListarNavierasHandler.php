<?php

declare(strict_types=1);

namespace Courier\Naviera\Application\ListarNavieras;

use Courier\Naviera\Domain\NavieraRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

/** Navieras para elegir en un formulario (por defecto solo las activas). */
final class ListarNavierasHandler implements QueryHandlerInterface
{
    public function __construct(private readonly NavieraRepositoryInterface $navieras)
    {
    }

    /** @return array<int, \Courier\Naviera\Domain\Naviera> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarNavierasQuery);

        return $this->navieras->findAll($query->soloActivas);
    }
}
