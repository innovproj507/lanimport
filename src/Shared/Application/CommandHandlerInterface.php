<?php

declare(strict_types=1);

namespace Courier\Shared\Application;

interface CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed;
}
