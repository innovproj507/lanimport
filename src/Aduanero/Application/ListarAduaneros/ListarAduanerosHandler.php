<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\ListarAduaneros;

use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ListarAduanerosHandler implements QueryHandlerInterface
{
    public function __construct(private readonly AduaneroRepositoryInterface $aduaneros)
    {
    }

    /** @return array<int, \Courier\Aduanero\Domain\Aduanero> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarAduanerosQuery);

        return $this->aduaneros->findAllActivos();
    }
}
