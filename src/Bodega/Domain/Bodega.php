<?php

declare(strict_types=1);

namespace Courier\Bodega\Domain;

use Courier\Shared\Domain\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Bodega fisica de la empresa. Cada carga ingresa en una; cada bulto (LPN) esta en una (cambia
 * con un traslado); cada ubicacion, OS/DO y reempaque es de una.
 */
final class Bodega
{
    /** La bodega que existia antes de manejar varias (id fijo, ver migracion 066). */
    public const PRINCIPAL = '00000000-0000-0000-0000-000000000001';

    private function __construct(
        private readonly string $id,
        private string $codigo,
        private string $nombre,
        private ?string $direccion,
        private bool $activo,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function registrar(string $id, string $codigo, string $nombre, ?string $direccion): self
    {
        $bodega = new self($id, '', '', null, true, new DateTimeImmutable());
        $bodega->actualizar($codigo, $nombre, $direccion, true);

        return $bodega;
    }

    public static function reconstituir(string $id, string $codigo, string $nombre, ?string $direccion, bool $activo, DateTimeImmutable $createdAt): self
    {
        return new self($id, $codigo, $nombre, $direccion, $activo, $createdAt);
    }

    public function actualizar(string $codigo, string $nombre, ?string $direccion, bool $activo): void
    {
        $codigo = strtoupper(trim($codigo));
        $nombre = trim($nombre);

        if (!preg_match('/^[A-Z0-9-]{1,20}$/', $codigo)) {
            throw new ValidationException('El codigo de la bodega es obligatorio: letras, numeros o guion (maximo 20). Ej: BP, COLON.');
        }

        if ($nombre === '') {
            throw new ValidationException('Escriba el nombre de la bodega.');
        }

        if (!$activo && $this->id === self::PRINCIPAL) {
            throw new ValidationException('La Bodega Principal no se puede desactivar.');
        }

        $this->codigo = $codigo;
        $this->nombre = mb_substr($nombre, 0, 120);
        $this->direccion = $direccion !== null && trim($direccion) !== '' ? mb_substr(trim($direccion), 0, 255) : null;
        $this->activo = $activo;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function direccion(): ?string
    {
        return $this->direccion;
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
