<?php

declare(strict_types=1);

namespace Courier\Naviera\Application\ListarNavieras;

use Courier\Shared\Application\QueryInterface;

final class ListarNavierasQuery implements QueryInterface
{
    public function __construct(
        public readonly bool $soloActivas = true,
    ) {
    }
}
