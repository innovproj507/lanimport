<?php

declare(strict_types=1);

namespace Courier\Auth\Application\Login;

use Courier\Shared\Application\CommandInterface;

final class LoginCommand implements CommandInterface
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $clientType,
        public readonly string $ipAddress,
        public readonly bool $recordar = false,
    ) {
    }
}
