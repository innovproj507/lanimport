<?php

declare(strict_types=1);

namespace Courier\Traslado\Application\CrearTraslado;

use Courier\Shared\Application\CommandInterface;

final class CrearTrasladoCommand implements CommandInterface
{
    /** @param array<int, string> $lpnIds bultos disponibles de la bodega origen */
    public function __construct(
        public readonly string $origenBodegaId,
        public readonly string $destinoBodegaId,
        public readonly array $lpnIds,
        public readonly string $usuarioId,
        public readonly ?string $nota = null,
    ) {
    }
}
