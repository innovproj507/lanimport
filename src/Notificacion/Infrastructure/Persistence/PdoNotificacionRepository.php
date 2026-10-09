<?php

declare(strict_types=1);

namespace Courier\Notificacion\Infrastructure\Persistence;

use Courier\Notificacion\Domain\Notificacion;
use Courier\Notificacion\Domain\NotificacionRepositoryInterface;
use PDO;

final class PdoNotificacionRepository implements NotificacionRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Notificacion $notificacion): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO notificaciones (id, carga_id, destinatario_email, canal, asunto, cuerpo, estado_envio, error_mensaje, created_at)
             VALUES (:id, :carga_id, :email, :canal, :asunto, :cuerpo, :estado, :error, :created_at)
             ON DUPLICATE KEY UPDATE estado_envio = VALUES(estado_envio), error_mensaje = VALUES(error_mensaje)'
        );

        $stmt->execute([
            ':id' => $notificacion->id(),
            ':carga_id' => $notificacion->cargaId(),
            ':email' => $notificacion->destinatarioEmail(),
            ':canal' => $notificacion->canal(),
            ':asunto' => $notificacion->asunto(),
            ':cuerpo' => $notificacion->cuerpo(),
            ':estado' => $notificacion->estadoEnvio()->value,
            ':error' => $notificacion->errorMensaje(),
            ':created_at' => $notificacion->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function listarPorCarga(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT destinatario_email, asunto, estado_envio, error_mensaje, created_at
             FROM notificaciones WHERE carga_id = :carga_id ORDER BY created_at DESC'
        );
        $stmt->execute([':carga_id' => $cargaId]);

        return array_map(
            static fn (array $row) => [
                'destinatario' => (string) $row['destinatario_email'],
                'asunto' => (string) $row['asunto'],
                'estado' => (string) $row['estado_envio'],
                'error' => $row['error_mensaje'] !== null ? (string) $row['error_mensaje'] : null,
                'fecha' => (string) $row['created_at'],
            ],
            $stmt->fetchAll(),
        );
    }
}
