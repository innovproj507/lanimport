<?php

declare(strict_types=1);

namespace Courier\Carga\Domain\ValueObject;

final class LineaCarga
{
    public function __construct(
        private readonly string $descripcion,
        private readonly string $unidadMedida,
        private readonly float $cantidad,
        private readonly float $ancho,
        private readonly float $alto,
        private readonly float $largo,
        private readonly float $peso,
        private readonly float $valor,
        private readonly ?string $marca = null,
        private readonly ?float $cubicajePies = null,
    ) {
    }

    /** @param array{descripcion: string, unidadMedida: string, cantidad: float, ancho: float, alto: float, largo: float, peso: float, valor: float, marca?: ?string, cubicajePies?: ?float} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['descripcion'],
            (string) $data['unidadMedida'],
            (float) $data['cantidad'],
            (float) $data['ancho'],
            (float) $data['alto'],
            (float) $data['largo'],
            (float) $data['peso'],
            (float) $data['valor'],
            isset($data['marca']) && $data['marca'] !== '' ? (string) $data['marca'] : null,
            isset($data['cubicajePies']) ? (float) $data['cubicajePies'] : null,
        );
    }

    public function cubicajePies(): float
    {
        return $this->cubicajePies ?? round(($this->ancho * $this->alto * $this->largo) / 28_316.846592, 6);
    }

    public function descripcion(): string
    {
        return $this->descripcion;
    }

    public function marca(): ?string
    {
        return $this->marca;
    }

    public function unidadMedida(): string
    {
        return $this->unidadMedida;
    }

    public function cantidad(): float
    {
        return $this->cantidad;
    }

    public function ancho(): float
    {
        return $this->ancho;
    }

    public function alto(): float
    {
        return $this->alto;
    }

    public function largo(): float
    {
        return $this->largo;
    }

    public function peso(): float
    {
        return $this->peso;
    }

    public function valor(): float
    {
        return $this->valor;
    }
}
