<?php

declare(strict_types=1);

namespace Courier\Naviera\Application\ActualizarNaviera;

use Courier\Shared\Application\CommandInterface;

final class ActualizarNavieraCommand implements CommandInterface
{
    public function __construct(
        public readonly string $navieraId,
        public readonly string $nombre,
        public readonly bool $activo,
    ) {
    }
}
