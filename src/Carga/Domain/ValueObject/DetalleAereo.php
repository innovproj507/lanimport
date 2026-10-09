<?php

declare(strict_types=1);

namespace Courier\Carga\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class DetalleAereo implements DetalleServicioInterface
{
    public function __construct(
        private readonly string $numeroVuelo,
        private readonly DateTimeImmutable $fechaEstimadaLlegada,
        private readonly string $aerolinea,
    ) {
        if (trim($numeroVuelo) === '' || trim($aerolinea) === '') {
            throw new ValidationException('Numero de vuelo y aerolinea son obligatorios para courier aereo.');
        }
    }

    public function numeroVuelo(): string
    {
        return $this->numeroVuelo;
    }

    public function aerolinea(): string
    {
        return $this->aerolinea;
    }

    public function fechaEstimadaLlegada(): ?DateTimeImmutable
    {
        return $this->fechaEstimadaLlegada;
    }

    public function toArray(): array
    {
        return [
            'numero_vuelo' => $this->numeroVuelo,
            'fecha_estimada_llegada' => $this->fechaEstimadaLlegada->format('Y-m-d'),
            'aerolinea' => $this->aerolinea,
        ];
    }
}
