<?php

declare(strict_types=1);

namespace Courier\Carga\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;

final class TrackingNumero
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));

        if (!preg_match('/^CRX-\d{4}-[A-Z0-9]{6}$/', $value)) {
            throw new ValidationException("Numero de tracking invalido: {$value}");
        }

        $this->value = $value;
    }

    public static function paraAnio(int $anio, string $sufijo): self
    {
        return new self(sprintf('CRX-%d-%s', $anio, $sufijo));
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
