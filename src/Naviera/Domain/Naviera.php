<?php

declare(strict_types=1);

namespace Courier\Naviera\Domain;

use Courier\Shared\Domain\AggregateRoot;
use Courier\Shared\Domain\Exception\ValidationException;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Linea naviera (Evergreen, Seaboard, Hapag-Lloyd...) que transporta los
 * contenedores de las salidas. Las inactivas no se ofrecen en salidas nuevas
 * pero se conservan para el historial.
 */
final class Naviera extends AggregateRoot
{
    public function __construct(
        private readonly Uuid $id,
        private string $nombre,
        private bool $activo,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function registrar(Uuid $id, string $nombre): self
    {
        return new self($id, self::validarNombre($nombre), true, new DateTimeImmutable());
    }

    public function actualizar(string $nombre, bool $activo): void
    {
        $this->nombre = self::validarNombre($nombre);
        $this->activo = $activo;
    }

    /** Se guarda en mayusculas para evitar duplicados como "Evergreen" / "EVERGREEN". */
    private static function validarNombre(string $nombre): string
    {
        $nombre = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $nombre) ?? $nombre));

        if ($nombre === '') {
            throw new ValidationException('Indique el nombre de la naviera.');
        }

        if (mb_strlen($nombre) > 120) {
            throw new ValidationException('El nombre de la naviera no puede tener mas de 120 caracteres.');
        }

        return $nombre;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
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
