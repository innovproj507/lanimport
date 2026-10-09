<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\VerReempaqueOrden;

use Courier\Reempaque\Domain\ReempaqueOrden;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Shared\Application\QueryHandlerInterface;
use Courier\Shared\Application\QueryInterface;

final class VerReempaqueOrdenHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ReempaqueOrdenRepositoryInterface $ordenes)
    {
    }

    public function handle(QueryInterface $query): ?ReempaqueOrden
    {
        assert($query instanceof VerReempaqueOrdenQuery);

        return $this->ordenes->findById($query->reempaqueOrdenId);
    }
}
