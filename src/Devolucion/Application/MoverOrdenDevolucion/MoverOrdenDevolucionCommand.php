<?php

declare(strict_types=1);

namespace Courier\Devolucion\Application\MoverOrdenDevolucion;

use Courier\Devolucion\Domain\DatosTransporte;
use Courier\Shared\Application\CommandInterface;

final class MoverOrdenDevolucionCommand implements CommandInterface
{
    public const ESCANEAR = 'escanear';
    public const DATOS = 'datos';
    public const QUITAR = 'quitar';
    public const CANCELAR = 'cancelar';
    public const CONFIRMAR = 'confirmar';

    public function __construct(
        public readonly string $ordenId,
        public readonly string $accion,
        public readonly string $usuarioId,
        public readonly ?string $codigo = null,
        public readonly ?string $cargaId = null,
        public readonly ?DatosTransporte $transporte = null,
        public readonly ?string $nota = null,
        public readonly ?string $motivo = null,
    ) {
    }
}
