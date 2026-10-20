<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\CerrarCajaReempaque;

use Courier\Shared\Application\CommandInterface;

final class CerrarCajaReempaqueCommand implements CommandInterface
{
    /** @param bool $descartarVacia true = la caja abierta esta vacia y se descarta (se abrio por error) */
    public function __construct(
        public readonly string $reempaqueOrdenId,
        public readonly bool $descartarVacia = false,
    ) {
    }
}
