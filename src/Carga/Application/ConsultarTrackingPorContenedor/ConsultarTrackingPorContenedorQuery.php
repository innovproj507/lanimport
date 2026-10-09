<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ConsultarTrackingPorContenedor;

use Courier\Shared\Application\QueryInterface;

final class ConsultarTrackingPorContenedorQuery implements QueryInterface
{
    public function __construct(public readonly string $numeroContenedor)
    {
    }
}
