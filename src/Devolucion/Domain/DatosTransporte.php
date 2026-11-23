<?php

declare(strict_types=1);

namespace Courier\Devolucion\Domain;

/** El camion que viene a buscar la devolucion (en lugar del contenedor de una salida). */
final class DatosTransporte
{
    public function __construct(
        public readonly ?string $empresa = null,
        public readonly ?string $chofer = null,
        public readonly ?string $placa = null,
        public readonly ?string $cedula = null,
    ) {
    }

    public static function desde(?string $empresa, ?string $chofer, ?string $placa, ?string $cedula): self
    {
        $limpiar = static fn (?string $v, int $max): ?string => $v !== null && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;

        return new self($limpiar($empresa, 150), $limpiar($chofer, 150), $limpiar($placa !== null ? mb_strtoupper($placa) : null, 30), $limpiar($cedula, 40));
    }

    public function vacio(): bool
    {
        return $this->empresa === null && $this->chofer === null && $this->placa === null && $this->cedula === null;
    }

    /** "Transportes X · Placa AB123 · Chofer Juan" para la bitacora. */
    public function resumen(): string
    {
        return implode(' · ', array_filter([
            $this->empresa,
            $this->placa !== null ? "placa {$this->placa}" : null,
            $this->chofer !== null ? "chofer {$this->chofer}" : null,
        ]));
    }
}
