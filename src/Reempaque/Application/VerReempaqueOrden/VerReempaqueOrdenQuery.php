<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\VerReempaqueOrden;

use Courier\Shared\Application\QueryInterface;

final class VerReempaqueOrdenQuery implements QueryInterface
{
    public function __construct(public readonly string $reempaqueOrdenId)
    {
    }
}
