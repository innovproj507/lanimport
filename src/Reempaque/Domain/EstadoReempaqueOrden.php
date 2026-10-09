<?php

declare(strict_types=1);

namespace Courier\Reempaque\Domain;

use Courier\Shared\Domain\Exception\ValidationException;

enum EstadoReempaqueOrden: string
{
    case PENDIENTE = 'pendiente';
    case COMPLETADO = 'completado';
    case CANCELADO = 'cancelado';

    /** @return array<int, self> */
    private static function transitions(self $estado): array
    {
        return match ($estado) {
            self::PENDIENTE => [self::COMPLETADO, self::CANCELADO],
            self::COMPLETADO => [],
            self::CANCELADO => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, self::transitions($this), true);
    }

    public function assertTransitionTo(self $next): void
    {
        if (!$this->canTransitionTo($next)) {
            throw new ValidationException(
                "No se puede pasar de '{$this->value}' a '{$next->value}'."
            );
        }
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::COMPLETADO => 'Completado',
            self::CANCELADO => 'Cancelado',
        };
    }
}
