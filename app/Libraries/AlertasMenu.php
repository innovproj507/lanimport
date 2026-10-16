<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Services;
use PDO;
use Throwable;

/**
 * Pendientes que se muestran en la campana de la barra superior, filtrados
 * por lo que el rol actual puede atender.
 */
final class AlertasMenu
{
    /** @return array<int, array{texto: string, total: int, href: string}> */
    public static function paraRol(?string $rol): array
    {
        try {
            /** @var PDO $pdo */
            $pdo = Services::courierContainer()->get(PDO::class);
            $alertas = [];

            if (in_array($rol, ['operaciones', 'gerente', 'admin'], true)) {
                // Cargas cuyo ultimo correo al cliente fallo (si luego se reenvio bien, ya no cuenta).
                $fallidos = $pdo->query(
                    "SELECT n.carga_id FROM notificaciones n
                     WHERE n.carga_id IS NOT NULL AND n.created_at = (SELECT MAX(n2.created_at) FROM notificaciones n2 WHERE n2.carga_id = n.carga_id)
                       AND n.estado_envio = 'fallido'
                     ORDER BY n.created_at DESC"
                )->fetchAll(PDO::FETCH_COLUMN);

                if ($fallidos !== []) {
                    $alertas[] = ['texto' => 'Correos al cliente que no se enviaron', 'total' => count($fallidos), 'href' => '/cargas/' . $fallidos[0]];
                }

                $porAprobar = (int) $pdo->query("SELECT COUNT(*) FROM clientes WHERE estado = 'pendiente'")->fetchColumn();

                if ($porAprobar > 0) {
                    $alertas[] = ['texto' => 'Clientes por aprobar', 'total' => $porAprobar, 'href' => '/clientes'];
                }
            }

            return $alertas;
        } catch (Throwable) {
            // La campana nunca debe tumbar la pagina (p.ej. migracion 048 aun no aplicada).
            return [];
        }
    }
}
