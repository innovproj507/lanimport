<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\ConsultarGenealogiaLpn;

use Courier\Shared\Application\QueryInterface;

final class ConsultarGenealogiaLpnQuery implements QueryInterface
{
    public function __construct(public readonly string $lpnId)
    {
    }
}
