<?php

declare(strict_types=1);

namespace Courier\Salida\Application\VerOrdenSalida;

use Courier\Shared\Application\QueryInterface;

final class VerOrdenSalidaQuery implements QueryInterface
{
    public function __construct(public readonly string $salidaOrdenId)
    {
    }
}
