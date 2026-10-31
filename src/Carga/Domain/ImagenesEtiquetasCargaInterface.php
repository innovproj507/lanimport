<?php

declare(strict_types=1);

namespace Courier\Carga\Domain;

/**
 * Las imagenes de las etiquetas LPN son lo que mas ocupa en disco. Cuando la carga ya salio
 * despachada se borran; si alguien vuelve a abrir su recepcion, se dibujan otra vez solas
 * (GenerarImagenesEtiquetasLpnHandler genera las que faltan).
 */
interface ImagenesEtiquetasCargaInterface
{
    /** @return int imagenes borradas */
    public function liberar(string $cargaId): int;
}
