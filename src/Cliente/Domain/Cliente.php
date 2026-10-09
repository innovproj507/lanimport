<?php

declare(strict_types=1);

namespace Courier\Cliente\Domain;

use Courier\Cliente\Domain\Exception\TransicionEstadoInvalidaException;
use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\ValueObject\EmailAddress;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Cliente extends AggregateRoot
{
    public function __construct(
        private readonly Uuid $id,
        private ?string $usuarioId,
        private string $nombre,
        private ?string $apellido,
        private EmailAddress $email,
        private ?string $telefono,
        private ?string $direccion,
        private ?string $empresa,
        private ?string $pais,
        private EstadoCliente $estado,
        private bool $activo,
        private readonly DateTimeImmutable $createdAt,
        private ?string $codigo = null,
        private ?int $secuencia = null,
    ) {
    }

    public static function registrar(
        Uuid $id,
        string $nombre,
        ?string $apellido,
        EmailAddress $email,
        ?string $telefono,
        ?string $direccion,
        ?string $empresa,
        ?string $pais,
        ?string $usuarioId = null,
    ): self {
        return new self(
            $id,
            $usuarioId,
            $nombre,
            $apellido,
            $email,
            $telefono,
            $direccion,
            $empresa,
            $pais,
            EstadoCliente::APROBADO,
            true,
            new DateTimeImmutable(),
        );
    }

    public static function solicitar(
        Uuid $id,
        string $nombre,
        ?string $apellido,
        EmailAddress $email,
        ?string $telefono,
        ?string $direccion,
        ?string $empresa,
        ?string $pais,
    ): self {
        return new self(
            $id,
            null,
            $nombre,
            $apellido,
            $email,
            $telefono,
            $direccion,
            $empresa,
            $pais,
            EstadoCliente::PENDIENTE,
            true,
            new DateTimeImmutable(),
        );
    }

    public function aprobar(string $usuarioId): void
    {
        if ($this->estado !== EstadoCliente::PENDIENTE) {
            throw new TransicionEstadoInvalidaException('Solo se pueden aprobar solicitudes pendientes.');
        }

        $this->estado = EstadoCliente::APROBADO;
        $this->usuarioId = $usuarioId;
    }

    public function rechazar(): void
    {
        if ($this->estado !== EstadoCliente::PENDIENTE) {
            throw new TransicionEstadoInvalidaException('Solo se pueden rechazar solicitudes pendientes.');
        }

        $this->estado = EstadoCliente::RECHAZADO;
    }

    public function desactivar(): void
    {
        $this->activo = false;
    }

    public function reactivar(): void
    {
        $this->activo = true;
    }

    public function actualizar(
        string $nombre,
        ?string $apellido,
        EmailAddress $email,
        ?string $telefono,
        ?string $direccion,
        ?string $empresa,
        ?string $pais,
    ): void {
        $this->nombre = $nombre;
        $this->apellido = $apellido;
        $this->email = $email;
        $this->telefono = $telefono;
        $this->direccion = $direccion;
        $this->empresa = $empresa;
        $this->pais = $pais;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function usuarioId(): ?string
    {
        return $this->usuarioId;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function apellido(): ?string
    {
        return $this->apellido;
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . ($this->apellido ?? ''));
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function telefono(): ?string
    {
        return $this->telefono;
    }

    public function direccion(): ?string
    {
        return $this->direccion;
    }

    public function empresa(): ?string
    {
        return $this->empresa;
    }

    public function pais(): ?string
    {
        return $this->pais;
    }

    public function estado(): EstadoCliente
    {
        return $this->estado;
    }

    public function activo(): bool
    {
        return $this->activo;
    }

    public function codigo(): ?string
    {
        return $this->codigo;
    }

    public function secuencia(): ?int
    {
        return $this->secuencia;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
