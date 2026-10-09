<?php

declare(strict_types=1);

namespace Courier\Auth\Domain;

use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\ValueObject\EmailAddress;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Usuario extends AggregateRoot
{
    public function __construct(
        private readonly Uuid $id,
        private string $nombre,
        private EmailAddress $email,
        private string $passwordHash,
        private Rol $rol,
        private bool $activo,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function registrar(
        Uuid $id,
        string $nombre,
        EmailAddress $email,
        string $passwordHash,
        Rol $rol,
    ): self {
        return new self($id, $nombre, $email, $passwordHash, $rol, true, new DateTimeImmutable());
    }

    public static function reconstituir(
        Uuid $id,
        string $nombre,
        EmailAddress $email,
        string $passwordHash,
        Rol $rol,
        bool $activo,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $nombre, $email, $passwordHash, $rol, $activo, $createdAt);
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function cambiarPassword(string $nuevoPasswordHash): void
    {
        $this->passwordHash = $nuevoPasswordHash;
    }

    public function actualizar(string $nombre, Rol $rol): void
    {
        $this->nombre = $nombre;
        $this->rol = $rol;
    }

    public function activar(): void
    {
        $this->activo = true;
    }

    public function desactivar(): void
    {
        $this->activo = false;
    }

    public function rol(): Rol
    {
        return $this->rol;
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
