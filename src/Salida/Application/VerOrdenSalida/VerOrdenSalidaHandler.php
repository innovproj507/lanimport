<?php

declare(strict_types=1);

namespace Courier\Salida\Application\VerOrdenSalida;

use Courier\Salida\Domain\SalidaOrden;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class VerOrdenSalidaHandler implements QueryHandlerInterface
{
    public function __construct(private readonly SalidaOrdenRepositoryInterface $ordenes)
    {
    }

    public function handle(QueryInterface $query): ?SalidaOrden
    {
        assert($query instanceof VerOrdenSalidaQuery);

        return $this->ordenes->findById($query->salidaOrdenId);
    }
}
