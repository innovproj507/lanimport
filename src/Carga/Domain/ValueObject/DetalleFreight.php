<?php

declare(strict_types=1);

namespace Courier\Carga\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class DetalleFreight implements DetalleServicioInterface
{
    public function __construct(
        private readonly string $numeroBl,
        private readonly string $tipoContenedor,
        private readonly float $pesoBruto,
        private readonly float $pesoNeto,
        private readonly ?DateTimeImmutable $fechaEstimadaLlegada = null,
    ) {
        if (trim($numeroBl) === '' || trim($tipoContenedor) === '') {
            throw new ValidationException('Numero de BL y tipo de contenedor son obligatorios para freight forwarding.');
        }

        if ($pesoBruto <= 0 || $pesoNeto <= 0 || $pesoNeto > $pesoBruto) {
            throw new ValidationException('Peso bruto y neto invalidos: el neto no puede superar al bruto.');
        }
    }

    public function numeroBl(): string
    {
        return $this->numeroBl;
    }

    public function tipoContenedor(): string
    {
        return $this->tipoContenedor;
    }

    public function pesoBruto(): float
    {
        return $this->pesoBruto;
    }

    public function pesoNeto(): float
    {
        return $this->pesoNeto;
    }

    public function fechaEstimadaLlegada(): ?DateTimeImmutable
    {
        return $this->fechaEstimadaLlegada;
    }

    public function toArray(): array
    {
        return [
            'numero_bl' => $this->numeroBl,
            'tipo_contenedor' => $this->tipoContenedor,
            'peso_bruto' => $this->pesoBruto,
            'peso_neto' => $this->pesoNeto,
            'fecha_estimada_llegada' => $this->fechaEstimadaLlegada?->format('Y-m-d'),
        ];
    }
}
