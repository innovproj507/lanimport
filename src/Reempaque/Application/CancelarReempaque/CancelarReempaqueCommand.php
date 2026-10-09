<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\CancelarReempaque;

use Courier\Shared\Application\CommandInterface;

final class CancelarReempaqueCommand implements CommandInterface
{
    public function __construct(public readonly string $reempaqueOrdenId)
    {
    }
}
