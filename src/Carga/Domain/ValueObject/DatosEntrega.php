<?php

declare(strict_types=1);

namespace Courier\Carga\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Datos del formulario de entrega que llena bodega al recibir la carga: quien la
 * trajo (empresa, chofer, placa), quien la entrego con su cedula y como viene la
 * mercancia. Todos son opcionales; "verificado por" es quien registra la carga.
 */
final class DatosEntrega
{
    public const TIPOS_MERCANCIA = ['cajas' => 'Cajas', 'saco' => 'Saco', 'bultos' => 'Bultos'];

    /** @param array<int, string> $tiposMercancia */
    public function __construct(
        public readonly ?string $empresaTransporte,
        public readonly ?string $chofer,
        public readonly ?string $placa,
        public readonly ?string $entregadoPor,
        public readonly ?string $cedula,
        public readonly array $tiposMercancia = [],
    ) {
        foreach ($tiposMercancia as $tipo) {
            if (!array_key_exists($tipo, self::TIPOS_MERCANCIA)) {
                throw new ValidationException("Tipo de mercancia invalido: {$tipo}.");
            }
        }
    }

    /**
     * Desde un arreglo de texto (formulario o BD); devuelve null si no hay ningun dato.
     *
     * @param array{empresaTransporte?: ?string, chofer?: ?string, placa?: ?string, entregadoPor?: ?string, cedula?: ?string, tiposMercancia?: array<int, string>} $datos
     */
    public static function desdeArreglo(array $datos): ?self
    {
        $texto = static function (?string $valor, int $max): ?string {
            $valor = trim((string) $valor);

            return $valor !== '' ? mb_substr($valor, 0, $max) : null;
        };

        $entrega = new self(
            $texto($datos['empresaTransporte'] ?? null, 190),
            $texto($datos['chofer'] ?? null, 190),
            ($p = $texto($datos['placa'] ?? null, 20)) !== null ? mb_strtoupper($p) : null,
            $texto($datos['entregadoPor'] ?? null, 190),
            $texto($datos['cedula'] ?? null, 40),
            array_values(array_unique($datos['tiposMercancia'] ?? [])),
        );

        return $entrega->estaVacia() ? null : $entrega;
    }

    public function estaVacia(): bool
    {
        return $this->empresaTransporte === null && $this->chofer === null && $this->placa === null
            && $this->entregadoPor === null && $this->cedula === null && $this->tiposMercancia === [];
    }

    public function tiposMercanciaTexto(): string
    {
        return implode(', ', array_map(static fn (string $t) => self::TIPOS_MERCANCIA[$t], $this->tiposMercancia));
    }
}
