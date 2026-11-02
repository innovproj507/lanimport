<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

use Courier\Carga\Domain\ValueObject\TrackingNumero;

interface BarcodeGeneratorInterface
{
    /** @return string Ruta publica relativa al PNG generado */
    public function generate(TrackingNumero $numero): string;

    /** Borra la imagen de la etiqueta de tracking (si existe). */
    public function eliminar(TrackingNumero $numero): void;
}
