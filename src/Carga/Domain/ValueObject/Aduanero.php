<?php

declare(strict_types=1);

namespace Courier\Carga\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;

final class Aduanero
{
    public function __construct(
        private readonly string $identificador,
        private readonly string $nombre,
    ) {
        if (trim($identificador) === '' || trim($nombre) === '') {
            throw new ValidationException('El identificador y nombre del aduanero son obligatorios.');
        }
    }

    public function identificador(): string
    {
        return $this->identificador;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function equals(self $other): bool
    {
        return $this->identificador === $other->identificador;
    }
}
