<?php

declare(strict_types=1);

namespace Courier\Carga\Application\GuardarActaRecepcion;

use Courier\Shared\Application\CommandInterface;

final class GuardarActaRecepcionCommand implements CommandInterface
{
    /**
     * @param string|null $actaId null = acta nueva; si no, corrige esa acta
     * @param string|null $cargaId solo para acta nueva de una carga ya registrada (el cliente sale de la carga)
     * @param array<int, string> $tiposMercancia
     */
    public function __construct(
        public readonly ?string $actaId,
        public readonly ?string $cargaId,
        public readonly string $clienteId,
        public readonly ?string $marca,
        public readonly ?string $empresaTransporte,
        public readonly ?string $choferNombre,
        public readonly ?string $placa,
        public readonly int $cantidadBultos,
        public readonly int $cantidadRollos,
        public readonly int $totalRecibido,
        public readonly array $tiposMercancia,
        public readonly ?string $descripcionMercancia,
        public readonly string $entregadoPor,
        public readonly string $entregadoPorCedula,
        public readonly string $usuarioId,
        public readonly ?string $rolUsuario,
    ) {
    }
}
