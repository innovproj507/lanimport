<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\TerminarReempaque;

use Courier\Shared\Application\CommandInterface;

final class TerminarReempaqueCommand implements CommandInterface
{
    public function __construct(
        public readonly string $reempaqueOrdenId,
    ) {
    }
}
