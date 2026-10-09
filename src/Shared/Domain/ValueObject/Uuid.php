<?php

declare(strict_types=1);

namespace Courier\Shared\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class Uuid
{
    private string $value;

    public function __construct(string $value)
    {
        if (!RamseyUuid::isValid($value)) {
            throw new ValidationException("Valor UUID invalido: {$value}");
        }

        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(RamseyUuid::uuid4()->toString());
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
