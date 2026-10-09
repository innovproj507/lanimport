<?php

declare(strict_types=1);

namespace Courier\Lpn\Domain;

use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Ubicacion
{
    public function __construct(
        private readonly Uuid $id,
        private readonly string $codigo,
        private ?string $zona,
        private ?string $pasillo,
        private ?string $nivel,
        private bool $activo,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function registrar(
        Uuid $id,
        string $codigo,
        ?string $zona,
        ?string $pasillo,
        ?string $nivel,
    ): self {
        return new self($id, $codigo, $zona, $pasillo, $nivel, true, new DateTimeImmutable());
    }

    public function actualizar(?string $zona, ?string $pasillo, ?string $nivel): void
    {
        $this->zona = $zona;
        $this->pasillo = $pasillo;
        $this->nivel = $nivel;
    }

    public function activar(): void
    {
        $this->activo = true;
    }

    public function desactivar(): void
    {
        $this->activo = false;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function zona(): ?string
    {
        return $this->zona;
    }

    public function pasillo(): ?string
    {
        return $this->pasillo;
    }

    public function nivel(): ?string
    {
        return $this->nivel;
    }

    public function activo(): bool
    {
        return $this->activo;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
