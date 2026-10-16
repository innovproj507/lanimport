<?php

declare(strict_types=1);

namespace Courier\Salida\Domain\ValueObject;

use Courier\Shared\Domain\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Datos del embarque de una salida: contenedor, sello, naviera, barco, fechas
 * de cargue y salida (ETD) y destino final. Todos opcionales: se van
 * completando a medida que se conocen.
 */
final class DatosEmbarque
{
    public function __construct(
        public readonly ?string $numeroContenedor,
        public readonly ?string $numeroSello,
        public readonly ?string $navieraId,
        public readonly ?string $navieraNombre,
        public readonly ?string $nombreBarco,
        public readonly ?DateTimeImmutable $fechaCargue,
        public readonly ?DateTimeImmutable $fechaSalidaEtd,
        public readonly ?string $destinoFinal,
    ) {
        // Estandar ISO: 4 letras + 7 digitos (el ultimo es de control). Se aceptan tambien 6
        // digitos porque hay documentos de navieras que traen el numero asi (ej. TGBU10548-6).
        if ($numeroContenedor !== null && preg_match('/^[A-Z]{4}\d{6,7}$/', $numeroContenedor) !== 1) {
            throw new ValidationException("Numero de contenedor invalido: {$numeroContenedor}. Deben ser 4 letras y 6 o 7 numeros (ej. TGBU1054866).");
        }

        if ($fechaCargue !== null && $fechaSalidaEtd !== null && $fechaSalidaEtd < $fechaCargue) {
            throw new ValidationException('La fecha de salida (ETD) no puede ser anterior a la fecha de cargue.');
        }
    }

    /**
     * Desde texto de formulario o BD. El contenedor acepta "TGBU10548-6" o "tgbu 105486"
     * (se quitan guiones y espacios); las fechas en formato aaaa-mm-dd.
     *
     * @param array<string, ?string> $d claves: numeroContenedor, numeroSello, navieraId, navieraNombre, nombreBarco, fechaCargue, fechaSalidaEtd, destinoFinal
     */
    public static function desdeArreglo(array $d): self
    {
        $texto = static function (?string $v, int $max): ?string {
            $v = trim((string) $v);

            return $v !== '' ? mb_substr($v, 0, $max) : null;
        };

        $fecha = static function (?string $v, string $campo): ?DateTimeImmutable {
            $v = trim((string) $v);

            if ($v === '') {
                return null;
            }

            $f = DateTimeImmutable::createFromFormat('!Y-m-d', substr($v, 0, 10));

            if ($f === false) {
                throw new ValidationException("Fecha invalida en {$campo}.");
            }

            return $f;
        };

        $contenedor = $texto($d['numeroContenedor'] ?? null, 30);

        return new self(
            $contenedor !== null ? strtoupper(preg_replace('/[\s-]+/', '', $contenedor) ?? $contenedor) : null,
            ($s = $texto($d['numeroSello'] ?? null, 40)) !== null ? mb_strtoupper($s) : null,
            $texto($d['navieraId'] ?? null, 36),
            $texto($d['navieraNombre'] ?? null, 120),
            ($b = $texto($d['nombreBarco'] ?? null, 120)) !== null ? mb_strtoupper($b) : null,
            $fecha($d['fechaCargue'] ?? null, 'fecha de cargue'),
            $fecha($d['fechaSalidaEtd'] ?? null, 'fecha de salida (ETD)'),
            $texto($d['destinoFinal'] ?? null, 190),
        );
    }

    public static function vacio(): self
    {
        return new self(null, null, null, null, null, null, null, null);
    }

    /** Contenedor como se escribe en los documentos: TGBU10548-6 (digito de control separado). */
    public function contenedorFormateado(): ?string
    {
        return $this->numeroContenedor !== null
            ? substr($this->numeroContenedor, 0, -1) . '-' . substr($this->numeroContenedor, -1)
            : null;
    }
}
