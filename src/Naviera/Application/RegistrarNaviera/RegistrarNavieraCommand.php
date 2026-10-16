<?php

declare(strict_types=1);

namespace Courier\Naviera\Application\RegistrarNaviera;

use Courier\Shared\Application\CommandInterface;

final class RegistrarNavieraCommand implements CommandInterface
{
    public function __construct(
        public readonly string $nombre,
    ) {
    }
}
