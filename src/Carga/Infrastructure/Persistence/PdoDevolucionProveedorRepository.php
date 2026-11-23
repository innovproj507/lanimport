<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Persistence;

use Courier\Carga\Domain\DevolucionProveedorRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use PDO;

final class PdoDevolucionProveedorRepository implements DevolucionProveedorRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function registrar(string $id, string $cargaId, int $bultos, bool $completa, string $motivo, ?string $usuarioId, array $lpnIds): void
    {
        $this->connection->prepare(
            'INSERT INTO devoluciones_proveedor (id, carga_id, bultos, completa, motivo, usuario_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$id, $cargaId, $bultos, $completa ? 1 : 0, mb_substr($motivo, 0, 500), $usuarioId]);

        $insert = $this->connection->prepare('INSERT INTO devolucion_proveedor_lpns (devolucion_id, lpn_id) VALUES (?, ?)');
        foreach (array_unique($lpnIds) as $lpnId) {
            $insert->execute([$id, $lpnId]);
        }
    }

    public function agregarDocumento(string $devolucionId, string $nombre, string $ruta, ?string $usuarioId): void
    {
        $this->connection->prepare(
            'INSERT INTO devolucion_proveedor_documentos (id, devolucion_id, nombre, ruta, usuario_id, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([(string) Uuid::generate(), $devolucionId, mb_substr($nombre, 0, 255), $ruta, $usuarioId]);
    }

    public function cargaIdDe(string $devolucionId): ?string
    {
        $stmt = $this->connection->prepare('SELECT carga_id FROM devoluciones_proveedor WHERE id = ?');
        $stmt->execute([$devolucionId]);
        $cargaId = $stmt->fetchColumn();

        return $cargaId !== false ? (string) $cargaId : null;
    }

    public function listarPorCarga(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT d.id, d.created_at, d.bultos, d.completa, d.motivo, u.nombre AS usuario
             FROM devoluciones_proveedor d
             LEFT JOIN usuarios u ON u.id = d.usuario_id
             WHERE d.carga_id = ?
             ORDER BY d.created_at DESC'
        );
        $stmt->execute([$cargaId]);
        $devoluciones = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $devoluciones[(string) $f['id']] = [
                'id' => (string) $f['id'],
                'fecha' => (string) $f['created_at'],
                'bultos' => (int) $f['bultos'],
                'completa' => (bool) $f['completa'],
                'motivo' => (string) $f['motivo'],
                'usuario' => $f['usuario'] !== null ? (string) $f['usuario'] : null,
                'etiquetas' => [],
                'documentos' => [],
            ];
        }

        if ($devoluciones === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($devoluciones), '?'));
        $ids = array_keys($devoluciones);

        $etiquetas = $this->connection->prepare(
            "SELECT dl.devolucion_id, l.codigo FROM devolucion_proveedor_lpns dl
             JOIN lpns l ON l.id = dl.lpn_id
             WHERE dl.devolucion_id IN ({$marcas})
             ORDER BY l.orden_en_carga, l.codigo"
        );
        $etiquetas->execute($ids);
        foreach ($etiquetas->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $devoluciones[(string) $f['devolucion_id']]['etiquetas'][] = (string) $f['codigo'];
        }

        $documentos = $this->connection->prepare(
            "SELECT d.id, d.devolucion_id, d.nombre, d.ruta, d.created_at, u.nombre AS usuario
             FROM devolucion_proveedor_documentos d
             LEFT JOIN usuarios u ON u.id = d.usuario_id
             WHERE d.devolucion_id IN ({$marcas})
             ORDER BY d.created_at"
        );
        $documentos->execute($ids);
        foreach ($documentos->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $devoluciones[(string) $f['devolucion_id']]['documentos'][] = [
                'id' => (string) $f['id'],
                'nombre' => (string) $f['nombre'],
                'ruta' => (string) $f['ruta'],
                'fecha' => (string) $f['created_at'],
                'usuario' => $f['usuario'] !== null ? (string) $f['usuario'] : null,
            ];
        }

        return array_values($devoluciones);
    }
}
