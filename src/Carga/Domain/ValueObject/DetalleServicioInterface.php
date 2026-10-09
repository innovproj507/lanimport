<?php

declare(strict_types=1);

namespace Courier\Carga\Domain\ValueObject;

use DateTimeImmutable;

interface DetalleServicioInterface
{
    public function fechaEstimadaLlegada(): ?DateTimeImmutable;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
