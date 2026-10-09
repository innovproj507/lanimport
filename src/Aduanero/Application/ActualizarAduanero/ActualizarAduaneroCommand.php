<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\ActualizarAduanero;

use Courier\Shared\Application\CommandInterface;

final class ActualizarAduaneroCommand implements CommandInterface
{
    public function __construct(
        public readonly string $aduaneroId,
        public readonly string $nombre,
        public readonly ?string $pais,
    ) {
    }
}
