<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Persistence;

use Courier\Carga\Domain\BitacoraCargaInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use PDO;
use Throwable;

final class PdoBitacoraCarga implements BitacoraCargaInterface
{
    /** @param callable(): ?string $usuarioActual quien hace el movimiento (la sesion web) */
    public function __construct(private readonly PDO $connection, private $usuarioActual)
    {
    }

    public function registrar(string $cargaId, string $tipo, string $detalle, ?string $salidaOrdenId = null): void
    {
        $this->seguro(function () use ($cargaId, $tipo, $detalle, $salidaOrdenId): void {
            $stmt = $this->connection->prepare('SELECT tracking_numero FROM cargas WHERE id = :id');
            $stmt->execute([':id' => $cargaId]);
            $tracking = $stmt->fetchColumn();

            if ($tracking !== false) {
                $this->insertar($cargaId, (string) $tracking, $tipo, $detalle, $salidaOrdenId);
            }
        });
    }

    public function registrarPorLpns(array $lpnIds, string $tipo, string $detalle, ?string $salidaOrdenId = null): void
    {
        $lpnIds = array_values(array_unique(array_map('strval', $lpnIds)));

        if ($lpnIds === []) {
            return;
        }

        $this->seguro(function () use ($lpnIds, $tipo, $detalle, $salidaOrdenId): void {
            $marcas = implode(',', array_fill(0, count($lpnIds), '?'));
            $stmt = $this->connection->prepare(
                "SELECT c.id, c.tracking_numero, COUNT(*) AS n
                 FROM (
                     SELECT l.carga_id FROM lpns l WHERE l.id IN ({$marcas}) AND l.carga_id IS NOT NULL
                     UNION ALL
                     SELECT o.carga_id FROM lpn_genealogia g JOIN lpns o ON o.id = g.lpn_origen_id WHERE g.lpn_destino_id IN ({$marcas})
                 ) x
                 JOIN cargas c ON c.id = x.carga_id
                 GROUP BY c.id, c.tracking_numero"
            );
            $stmt->execute([...$lpnIds, ...$lpnIds]);

            foreach ($stmt->fetchAll() as $r) {
                $this->insertar((string) $r['id'], (string) $r['tracking_numero'], $tipo, str_replace('{n}', (string) $r['n'], $detalle), $salidaOrdenId);
            }
        });
    }

    public function archivarEliminada(string $cargaId, ?string $motivo): void
    {
        $stmt = $this->connection->prepare(
            "SELECT c.*, TRIM(CONCAT(COALESCE(cl.nombre, ''), ' ', COALESCE(cl.apellido, ''))) AS cliente_nombre,
                    COALESCE(a.nombre, c.aduanero_nombre, c.aduanero_identificador) AS aduanero_txt,
                    COALESCE(p.nombre, c.proveedor) AS proveedor_txt, u.nombre AS registrado_por_nombre
             FROM cargas c
             LEFT JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN aduaneros a ON a.identificador = c.aduanero_identificador
             LEFT JOIN proveedores p ON p.identificador = c.proveedor_identificador
             LEFT JOIN usuarios u ON u.id = c.registrado_por_usuario_id
             WHERE c.id = :id"
        );
        $stmt->execute([':id' => $cargaId]);
        $c = $stmt->fetch();

        if ($c === false) {
            return;
        }

        $lineas = $this->connection->prepare('SELECT descripcion, marca, unidad_medida, cantidad, cubicaje_p3, peso, valor FROM carga_lineas WHERE carga_id = :id ORDER BY orden');
        $lineas->execute([':id' => $cargaId]);
        $historial = $this->connection->prepare(
            'SELECT h.estado, h.comentario, h.fecha, u.nombre AS usuario FROM tracking_historial h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.carga_id = :id ORDER BY h.fecha'
        );
        $historial->execute([':id' => $cargaId]);

        $usuarioId = ($this->usuarioActual)();
        $usuarioNombre = null;

        if ($usuarioId !== null) {
            $u = $this->connection->prepare('SELECT nombre FROM usuarios WHERE id = :id');
            $u->execute([':id' => $usuarioId]);
            $usuarioNombre = $u->fetchColumn() ?: null;
        }

        $motivo = $motivo !== null && trim($motivo) !== '' ? mb_substr(trim($motivo), 0, 500) : null;

        $this->connection->prepare(
            'INSERT INTO cargas_eliminadas (id, tracking_numero, cliente, aduanero, proveedor, fecha_ingreso, registrado_por, motivo,
                 eliminada_por_usuario_id, eliminada_por, eliminada_at, datos)
             VALUES (:id, :tracking, :cliente, :aduanero, :proveedor, :fecha_ingreso, :registrado_por, :motivo, :usuario_id, :usuario, NOW(), :datos)
             ON DUPLICATE KEY UPDATE motivo = VALUES(motivo), eliminada_at = VALUES(eliminada_at), datos = VALUES(datos)'
        )->execute([
            ':id' => $cargaId,
            ':tracking' => $c['tracking_numero'],
            ':cliente' => $c['cliente_nombre'],
            ':aduanero' => $c['aduanero_txt'],
            ':proveedor' => $c['proveedor_txt'],
            ':fecha_ingreso' => $c['fecha_ingreso'],
            ':registrado_por' => $c['registrado_por_nombre'],
            ':motivo' => $motivo,
            ':usuario_id' => $usuarioId,
            ':usuario' => $usuarioNombre,
            ':datos' => json_encode([
                'estado' => $c['estado'],
                'tipoServicio' => $c['tipo_servicio'],
                'notas' => $c['notas'],
                'contenedor' => $c['numero_contenedor'],
                'lineas' => $lineas->fetchAll(),
                'historial' => $historial->fetchAll(),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->insertar($cargaId, (string) $c['tracking_numero'], 'carga_eliminada', 'Carga eliminada' . ($motivo !== null ? ". Motivo: {$motivo}" : ''), null);
    }

    private function insertar(string $cargaId, string $tracking, string $tipo, string $detalle, ?string $salidaOrdenId): void
    {
        $this->connection->prepare(
            'INSERT INTO carga_movimientos (id, carga_id, tracking_numero, tipo, detalle, salida_orden_id, usuario_id, created_at)
             VALUES (:id, :carga_id, :tracking, :tipo, :detalle, :orden, :usuario, NOW())'
        )->execute([
            ':id' => (string) Uuid::generate(),
            ':carga_id' => $cargaId,
            ':tracking' => $tracking,
            ':tipo' => $tipo,
            ':detalle' => mb_substr($detalle, 0, 500),
            ':orden' => $salidaOrdenId,
            ':usuario' => ($this->usuarioActual)(),
        ]);
    }

    /** La bitacora no debe tumbar la operacion (p.ej. si la migracion aun no se aplico). */
    private function seguro(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            error_log('Bitacora de cargas: ' . $e->getMessage());
        }
    }
}
