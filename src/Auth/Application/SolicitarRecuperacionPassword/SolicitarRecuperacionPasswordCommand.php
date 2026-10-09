<?php

declare(strict_types=1);

namespace Courier\Auth\Application\SolicitarRecuperacionPassword;

use Courier\Shared\Application\CommandInterface;

final class SolicitarRecuperacionPasswordCommand implements CommandInterface
{
    public function __construct(
        public readonly string $email,
    ) {
    }
}
