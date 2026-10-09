<?php

declare(strict_types=1);

namespace Courier\Salida\Domain;

use DateTimeImmutable;

final class SalidaLinea
{
    public function __construct(
        private readonly string $lpnId,
        private readonly string $codigoLpn,
        private bool $escaneado = false,
        private ?DateTimeImmutable $escaneadoAt = null,
    ) {
    }

    public function marcarEscaneado(): void
    {
        $this->escaneado = true;
        $this->escaneadoAt = new DateTimeImmutable();
    }

    public function lpnId(): string
    {
        return $this->lpnId;
    }

    public function codigoLpn(): string
    {
        return $this->codigoLpn;
    }

    public function escaneado(): bool
    {
        return $this->escaneado;
    }

    public function escaneadoAt(): ?DateTimeImmutable
    {
        return $this->escaneadoAt;
    }
}
