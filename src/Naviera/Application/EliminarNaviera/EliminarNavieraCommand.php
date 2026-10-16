<?php

declare(strict_types=1);

namespace Courier\Naviera\Application\EliminarNaviera;

use Courier\Shared\Application\CommandInterface;

final class EliminarNavieraCommand implements CommandInterface
{
    public function __construct(
        public readonly string $navieraId,
    ) {
    }
}
