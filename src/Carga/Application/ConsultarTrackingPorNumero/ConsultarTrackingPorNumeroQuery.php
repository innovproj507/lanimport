<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ConsultarTrackingPorNumero;

use Courier\Shared\Application\QueryInterface;

final class ConsultarTrackingPorNumeroQuery implements QueryInterface
{
    public function __construct(public readonly string $trackingNumero)
    {
    }
}
