<?php

declare(strict_types=1);

namespace Courier\Reporte\Application\ObtenerReporteOperativo;

use Courier\Shared\Application\QueryInterface;

final class ObtenerReporteOperativoQuery implements QueryInterface
{
    public function __construct(
        public readonly string $desde,
        public readonly string $hasta,
    ) {
    }
}
