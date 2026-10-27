<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Services;
use PDO;

/**
 * "Huella" de los datos que muestra una pantalla: un hash que cambia cuando alguien
 * (otro usuario, la PDA) modifica algo. Las pantallas la consultan cada pocos segundos
 * (public/assets/js/vivo.js) y solo se refrescan cuando cambia, asi la consulta es liviana.
 *
 * Recursos:
 *  - global: cargas, LPN, salidas, reempaques, fotos, avisos y clientes (listados, dashboard, campana).
 *  - salida:{id}: un DO (estado, embarque, sus bultos y lo escaneado, fotos).
 */
final class HuellaCambios
{
    /**
     * @param array<int, string> $recursos
     * @return array<string, string|null> recurso => huella (null si no existe o no se reconoce)
     */
    public static function de(array $recursos): array
    {
        /** @var PDO $pdo */
        $pdo = Services::courierContainer()->get(PDO::class);
        $huellas = [];

        foreach (array_slice(array_unique($recursos), 0, 5) as $recurso) {
            $huellas[$recurso] = match (true) {
                $recurso === 'global' => self::global($pdo),
                str_starts_with($recurso, 'salida:') => self::salida($pdo, substr($recurso, 7)),
                default => null,
            };
        }

        return $huellas;
    }

    private static function global(PDO $pdo): string
    {
        $fila = $pdo->query(
            "SELECT
                (SELECT CONCAT(COUNT(*), '-', IFNULL(MAX(updated_at), '')) FROM cargas),
                (SELECT CONCAT(COUNT(*), '-', IFNULL(MAX(updated_at), '')) FROM lpns),
                (SELECT CONCAT(COUNT(*), '-', IFNULL(SUM(CRC32(CONCAT_WS(',', estado, nota, cliente_id, numero_contenedor))), 0)) FROM salida_ordenes),
                (SELECT CONCAT(COUNT(*), '-', IFNULL(SUM(escaneado), 0), '-', IFNULL(MAX(escaneado_at), '')) FROM salida_lineas),
                (SELECT CONCAT(COUNT(*), '-', IFNULL(SUM(CRC32(estado)), 0)) FROM reempaque_ordenes),
                (SELECT COUNT(*) FROM fotos_evidencia),
                (SELECT COUNT(*) FROM notificaciones),
                (SELECT CONCAT(COUNT(*), '-', IFNULL(SUM(estado = 'pendiente'), 0)) FROM clientes)"
        )->fetch(PDO::FETCH_NUM);

        return md5(implode('|', $fila ?: []));
    }

    private static function salida(PDO $pdo, string $ordenId): ?string
    {
        $stmt = $pdo->prepare(
            "SELECT CONCAT_WS('|', o.estado, o.nota, o.cliente_id, o.numero_contenedor, o.numero_sello, o.naviera_id,
                    o.nombre_barco, o.fecha_cargue, o.fecha_salida_etd, o.destino_final,
                    (SELECT CONCAT(COUNT(*), '-', IFNULL(SUM(l.escaneado), 0), '-', IFNULL(SUM(CRC32(l.lpn_id)), 0))
                     FROM salida_lineas l WHERE l.salida_orden_id = o.id),
                    (SELECT COUNT(*) FROM fotos_evidencia f WHERE f.salida_orden_id = o.id))
             FROM salida_ordenes o WHERE o.id = :id"
        );
        $stmt->execute([':id' => $ordenId]);
        $valor = $stmt->fetchColumn();

        return $valor === false ? null : md5((string) $valor);
    }
}
