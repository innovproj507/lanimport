<?php

declare(strict_types=1);

namespace Courier\Shared\Domain;

/**
 * Bodega con la que trabaja el usuario actual. Los listados (cargas, inventario, recepcion,
 * OS/DO, reempaques, ubicaciones, app de bodega) se filtran por ella.
 */
interface BodegaActivaInterface
{
    /** Bodega por la que filtrar, o null = todas. */
    public function id(): ?string;

    /** Bodega fija del usuario (personal de una bodega), o null si puede ver todas y elegir. */
    public function fija(): ?string;

    /** Si el usuario puede ver/operar algo de esa bodega. */
    public function permite(?string $bodegaId): bool;
}
