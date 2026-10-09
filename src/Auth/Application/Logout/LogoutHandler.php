<?php

declare(strict_types=1);

namespace Courier\Auth\Application\Logout;

use Courier\Auth\Infrastructure\Security\SessionManager;

final class LogoutHandler
{
    public function __construct(private readonly SessionManager $sessionManager)
    {
    }

    public function handle(): void
    {
        $this->sessionManager->destroy();
    }
}
