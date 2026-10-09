<?php

declare(strict_types=1);

namespace Courier\Notificacion\Domain;

interface NotificacionRepositoryInterface
{
    public function save(Notificacion $notificacion): void;

    /**
     * Historial de correos enviados por una carga, mas recientes primero.
     *
     * @return array<int, array{destinatario: string, asunto: string, estado: string, error: ?string, fecha: string}>
     */
    public function listarPorCarga(string $cargaId): array;
}
