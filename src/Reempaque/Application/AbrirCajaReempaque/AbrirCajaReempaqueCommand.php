<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\AbrirCajaReempaque;

use Courier\Shared\Application\CommandInterface;

final class AbrirCajaReempaqueCommand implements CommandInterface
{
    public function __construct(
        public readonly string $reempaqueOrdenId,
    ) {
    }
}
