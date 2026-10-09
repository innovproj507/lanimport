<?php

declare(strict_types=1);

namespace Courier\Shared\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;

final class EmailAddress
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException("Direccion de correo invalida: {$value}");
        }

        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return strcasecmp($this->value, $other->value) === 0;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
