<?php

declare(strict_types=1);

namespace Courier\Evidencia\Application\EliminarFotoEvidencia;

use Courier\Shared\Application\CommandInterface;

final class EliminarFotoEvidenciaCommand implements CommandInterface
{
    public function __construct(public readonly string $fotoId)
    {
    }
}
