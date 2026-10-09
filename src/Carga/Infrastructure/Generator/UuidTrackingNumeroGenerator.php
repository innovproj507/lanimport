<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Generator;

use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\TrackingNumeroGeneratorInterface;
use Courier\Carga\Domain\ValueObject\TrackingNumero;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class UuidTrackingNumeroGenerator implements TrackingNumeroGeneratorInterface
{
    private const MAX_INTENTOS = 3;

    public function __construct(private readonly CargaRepositoryInterface $repository)
    {
    }

    public function generate(): TrackingNumero
    {
        $anio = (int) date('Y');

        for ($intento = 0; $intento < self::MAX_INTENTOS; $intento++) {
            $sufijo = strtoupper(substr(str_replace('-', '', Uuid::uuid4()->toString()), 0, 6));
            $numero = TrackingNumero::paraAnio($anio, $sufijo);

            if (!$this->repository->existsTrackingNumero($numero)) {
                return $numero;
            }
        }

        throw new RuntimeException('No se pudo generar un numero de tracking unico tras varios intentos.');
    }
}
