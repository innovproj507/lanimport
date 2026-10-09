<?php

declare(strict_types=1);

namespace Courier\Pais\Application\ListarPaises;

use Courier\Pais\Domain\PaisRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ListarPaisesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PaisRepositoryInterface $paises)
    {
    }

    /** @return array<int, \Courier\Pais\Domain\Pais> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarPaisesQuery);

        return $this->paises->findAll();
    }
}
