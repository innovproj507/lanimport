<?php

declare(strict_types=1);

namespace Courier\Salida\Application\CancelarOrdenSalida;

use Courier\Shared\Application\CommandInterface;

final class CancelarOrdenSalidaCommand implements CommandInterface
{
    public function __construct(public readonly string $salidaOrdenId)
    {
    }
}
