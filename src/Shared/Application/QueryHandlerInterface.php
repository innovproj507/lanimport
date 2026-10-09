<?php

declare(strict_types=1);

namespace Courier\Shared\Application;

interface QueryHandlerInterface
{
    public function handle(QueryInterface $query): mixed;
}
