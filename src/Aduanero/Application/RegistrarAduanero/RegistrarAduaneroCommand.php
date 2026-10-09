<?php

declare(strict_types=1);

namespace Courier\Aduanero\Application\RegistrarAduanero;

use Courier\Shared\Application\CommandInterface;

final class RegistrarAduaneroCommand implements CommandInterface
{
    public function __construct(
        public readonly string $nombre,
        public readonly ?string $pais,
    ) {
    }
}
