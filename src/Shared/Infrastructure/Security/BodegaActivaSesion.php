<?php

declare(strict_types=1);

namespace Courier\Shared\Infrastructure\Security;

use Courier\Bodega\Domain\BodegaRepositoryInterface;
use Courier\Shared\Domain\BodegaActivaInterface;

/**
 * Bodega activa desde la sesion web. La bodega fija del usuario se lee de la base en cada
 * peticion (si Owner se la cambia, aplica al instante). Sin sesion (CLI, cron) = todas.
 */
final class BodegaActivaSesion implements BodegaActivaInterface
{
    public const CLAVE = 'bodega_activa';

    private bool $leida = false;
    private ?string $fija = null;

    public function __construct(private readonly BodegaRepositoryInterface $bodegas)
    {
    }

    public function fija(): ?string
    {
        if (!$this->leida) {
            $this->leida = true;
            $usuarioId = $_SESSION['usuario_id'] ?? null;
            $this->fija = $usuarioId !== null ? $this->bodegas->bodegaDeUsuario((string) $usuarioId) : null;
        }

        return $this->fija;
    }

    public function id(): ?string
    {
        $elegida = $_SESSION[self::CLAVE] ?? null;

        return $this->fija() ?? ($elegida !== null && $elegida !== '' ? (string) $elegida : null);
    }

    public function permite(?string $bodegaId): bool
    {
        return $this->fija() === null || $bodegaId === null || $bodegaId === $this->fija();
    }
}
