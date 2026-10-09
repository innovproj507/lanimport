<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

use Courier\Carga\Domain\ValueObject\TrackingNumero;

interface TrackingNumeroGeneratorInterface
{
    public function generate(): TrackingNumero;
}
