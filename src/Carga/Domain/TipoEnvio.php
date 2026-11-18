<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

use Courier\Shared\Domain\Exception\ValidationException;

/**
 * Como SALE la carga hacia el cliente (se elige al registrarla). Distinto de TipoServicio,
 * que es un campo heredado del sistema original y no se usa en la web.
 */
enum TipoEnvio: string
{
    case MARITIMO = 'maritimo';
    case AEREO = 'aereo';
    case TERRESTRE = 'terrestre';

    public function label(): string
    {
        return match ($this) {
            self::MARITIMO => 'Maritima',
            self::AEREO => 'Aerea',
            self::TERRESTRE => 'Terrestre',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::MARITIMO => '🚢',
            self::AEREO => '✈️',
            self::TERRESTRE => '🚛',
        };
    }

    /** Valor del formulario: vacio o desconocido es un error (el campo es obligatorio). */
    public static function desdeFormulario(?string $valor): self
    {
        $tipo = self::tryFrom(strtolower(trim((string) $valor)));

        if ($tipo === null) {
            throw new ValidationException('Seleccione el tipo de envio: Maritima, Aerea o Terrestre.');
        }

        return $tipo;
    }

    /** @return array<string, string> valor => etiqueta */
    public static function opciones(): array
    {
        $opciones = [];
        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->label();
        }

        return $opciones;
    }
}
