<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\EliminarAduanero;

use Courier\Shared\Application\CommandInterface;

final class EliminarAduaneroCommand implements CommandInterface
{
    public function __construct(
        public readonly string $aduaneroId,
    ) {
    }
}
