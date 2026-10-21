<?php

declare(strict_types=1);

namespace Courier\Carga\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Numero de tracking de una carga: LAN-AAAA-XXXXXX. Antes era CRX-AAAA-XXXXXX y todas las cargas
 * se renombraron (migracion 060) conservando el resto del numero; un CRX viejo (en un correo o
 * una etiqueta ya impresa) se traduce solo a su LAN, asi que sigue encontrando la carga.
 */
final class TrackingNumero
{
    public const PREFIJO = 'LAN';

    private const PREFIJO_ANTERIOR = 'CRX';

    private string $value;

    public function __construct(string $value)
    {
        // Las pistolas con teclado en espanol escriben "-" como "'" (o "?"/"_").
        $value = self::normalizarBusqueda(strtoupper(str_replace(["'", '?', '_'], '-', trim($value))));

        if (!preg_match('/^LAN-\d{4}-[A-Z0-9]{6}$/', $value)) {
            throw new ValidationException("Numero de tracking invalido: {$value}");
        }

        $this->value = $value;
    }

    /** "CRX-2026-..." (numero anterior) -> "LAN-2026-..."; cualquier otro texto queda igual. Para buscar. */
    public static function normalizarBusqueda(string $texto): string
    {
        return (string) preg_replace('/^\s*' . self::PREFIJO_ANTERIOR . '(?=[\s\-\'?_]|\d|$)/i', self::PREFIJO, $texto);
    }

    public static function paraAnio(int $anio, string $sufijo): self
    {
        return new self(sprintf('%s-%d-%s', self::PREFIJO, $anio, $sufijo));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
