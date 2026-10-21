<?php

declare(strict_types=1);

namespace Courier\Salida\Application\MoverCargasSalida;

use Courier\Shared\Application\CommandInterface;

final class MoverCargasSalidaCommand implements CommandInterface
{
    /**
     * @param string|null $destinoId salida a la que pasan; null = se crea un DO nuevo
     * @param array<int, string>|null $grupos cargas a mover (cargaId, o "caja-<lpnId>" de una caja
     *        reempacada, como en avancePorCarga); null = todo el DO (unir)
     */
    public function __construct(
        public readonly string $origenId,
        public readonly ?string $destinoId,
        public readonly ?array $grupos,
        public readonly string $usuarioId,
    ) {
    }
}
