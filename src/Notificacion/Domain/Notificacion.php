<?php

declare(strict_types=1);

namespace Courier\Notificacion\Domain;

use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class Notificacion
{
    private function __construct(
        private readonly Uuid $id,
        private readonly ?string $cargaId,
        private readonly string $destinatarioEmail,
        private readonly string $canal,
        private readonly string $asunto,
        private readonly string $cuerpo,
        private EstadoEnvio $estadoEnvio,
        private ?string $errorMensaje,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function crear(
        ?string $cargaId,
        string $destinatarioEmail,
        string $asunto,
        string $cuerpo,
        string $canal = 'email',
    ): self {
        return new self(
            Uuid::generate(),
            $cargaId,
            $destinatarioEmail,
            $canal,
            $asunto,
            $cuerpo,
            EstadoEnvio::PENDIENTE,
            null,
            new DateTimeImmutable(),
        );
    }

    public function marcarEnviado(): void
    {
        $this->estadoEnvio = EstadoEnvio::ENVIADO;
        $this->errorMensaje = null;
    }

    public function marcarFallido(string $mensaje): void
    {
        $this->estadoEnvio = EstadoEnvio::FALLIDO;
        $this->errorMensaje = $mensaje;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function cargaId(): ?string
    {
        return $this->cargaId;
    }

    public function destinatarioEmail(): string
    {
        return $this->destinatarioEmail;
    }

    public function canal(): string
    {
        return $this->canal;
    }

    public function asunto(): string
    {
        return $this->asunto;
    }

    public function cuerpo(): string
    {
        return $this->cuerpo;
    }

    public function estadoEnvio(): EstadoEnvio
    {
        return $this->estadoEnvio;
    }

    public function errorMensaje(): ?string
    {
        return $this->errorMensaje;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
