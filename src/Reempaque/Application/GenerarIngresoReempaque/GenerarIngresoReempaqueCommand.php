<?php

declare(strict_types=1);

namespace Courier\Reempaque\Application\GenerarIngresoReempaque;

use Courier\Shared\Application\CommandInterface;

final class GenerarIngresoReempaqueCommand implements CommandInterface
{
    /**
     * @param array<string, array{ancho: float, alto: float, largo: float, peso: float, p3: ?float}> $medidas cajaLpnId => medidas (cm, kg, p3)
     */
    public function __construct(
        public readonly string $ordenId,
        public readonly array $medidas,
        public readonly string $tipoEnvio,
        public readonly string $descripcion,
        public readonly float $valorTotal,
        public readonly ?string $notas,
        public readonly string $usuarioId,
    ) {
    }
}
