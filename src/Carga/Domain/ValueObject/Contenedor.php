<?php

declare(strict_types=1);

namespace Courier\Carga\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;

final class Contenedor
{
    private string $numero;

    public function __construct(string $numero)
    {
        $numero = strtoupper(trim($numero));

        if (!preg_match('/^[A-Z]{4}\d{7}$/', $numero)) {
            throw new ValidationException("Numero de contenedor invalido: {$numero}");
        }

        $this->numero = $numero;
    }

    public function numero(): string
    {
        return $this->numero;
    }

    public function equals(self $other): bool
    {
        return $this->numero === $other->numero;
    }

    public function __toString(): string
    {
        return $this->numero;
    }
}
