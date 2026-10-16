<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\CambiarEstadoAduanero;

use Courier\Shared\Application\CommandInterface;

final class CambiarEstadoAduaneroCommand implements CommandInterface
{
    public function __construct(
        public readonly string $aduaneroId,
        public readonly bool $activo,
    ) {
    }
}
