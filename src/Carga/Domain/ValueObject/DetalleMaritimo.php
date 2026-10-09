<?php

declare(strict_types=1);

namespace Courier\Carga\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class DetalleMaritimo implements DetalleServicioInterface
{
    public function __construct(
        private readonly string $numeroBuque,
        private readonly DateTimeImmutable $fechaEstimadaLlegada,
        private readonly string $puertoOrigen,
        private readonly string $puertoDestino,
    ) {
        if (trim($numeroBuque) === '' || trim($puertoOrigen) === '' || trim($puertoDestino) === '') {
            throw new ValidationException('Numero de buque, puerto de origen y destino son obligatorios para courier maritimo.');
        }
    }

    public function numeroBuque(): string
    {
        return $this->numeroBuque;
    }

    public function puertoOrigen(): string
    {
        return $this->puertoOrigen;
    }

    public function puertoDestino(): string
    {
        return $this->puertoDestino;
    }

    public function fechaEstimadaLlegada(): ?DateTimeImmutable
    {
        return $this->fechaEstimadaLlegada;
    }

    public function toArray(): array
    {
        return [
            'numero_buque' => $this->numeroBuque,
            'fecha_estimada_llegada' => $this->fechaEstimadaLlegada->format('Y-m-d'),
            'puerto_origen' => $this->puertoOrigen,
            'puerto_destino' => $this->puertoDestino,
        ];
    }
}
