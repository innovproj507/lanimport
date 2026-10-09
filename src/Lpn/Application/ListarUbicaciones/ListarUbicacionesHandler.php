<?php

declare(strict_types=1);

namespace Courier\Lpn\Application\ListarUbicaciones;

use Courier\Lpn\Domain\UbicacionRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ListarUbicacionesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly UbicacionRepositoryInterface $ubicaciones)
    {
    }

    /** @return array<int, \Courier\Lpn\Domain\Ubicacion> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarUbicacionesQuery);

        return $this->ubicaciones->findAll();
    }
}
