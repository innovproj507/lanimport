<?php

declare(strict_types=1);

namespace Courier\Auth\Application\RestablecerPassword;

use Courier\Shared\Application\CommandInterface;

final class RestablecerPasswordCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly string $nuevaPassword,
    ) {
    }
}
