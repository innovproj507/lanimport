<?php

declare(strict_types=1);

namespace Courier\Salida\Application\ListarOrdenesSalida;

use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class ListarOrdenesSalidaHandler implements QueryHandlerInterface
{
    public function __construct(private readonly SalidaOrdenRepositoryInterface $ordenes)
    {
    }

    /** @return array<int, \Courier\Salida\Domain\SalidaOrden> */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListarOrdenesSalidaQuery);

        return $this->ordenes->search([
            'estado' => $query->estado,
            'clienteId' => $query->clienteId,
        ]);
    }
}
