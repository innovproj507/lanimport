<?php

declare(strict_types=1);

namespace Courier\Devolucion\Infrastructure\Persistence;

use Courier\Devolucion\Domain\DatosTransporte;
use Courier\Devolucion\Domain\EstadoDevolucion;
use Courier\Devolucion\Domain\OrdenDevolucion;
use Courier\Devolucion\Domain\OrdenDevolucionRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

/** Tabla devoluciones_proveedor (la orden) y devolucion_proveedor_lpns (sus bultos). */
final class PdoOrdenDevolucionRepository implements OrdenDevolucionRepositoryInterface
{
    private const CLIENTE = "TRIM(CONCAT(COALESCE(cl.nombre, ''), ' ', COALESCE(cl.apellido, '')))";

    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(OrdenDevolucion $orden): void
    {
        $existe = $this->connection->prepare('SELECT 1 FROM devoluciones_proveedor WHERE id = ?');
        $existe->execute([$orden->id()]);
        $t = $orden->transporte();
        $datos = [
            ':estado' => $orden->estado()->value,
            ':motivo' => $orden->motivo(),
            ':nota' => $orden->nota(),
            ':empresa' => $t->empresa,
            ':chofer' => $t->chofer,
            ':placa' => $t->placa,
            ':cedula' => $t->cedula,
            ':bultos' => count($orden->lineas()),
            ':confirmado_por' => $orden->confirmadoPorUsuarioId(),
            ':confirmed_at' => $orden->confirmedAt()?->format('Y-m-d H:i:s'),
            ':id' => $orden->id(),
        ];

        if ($existe->fetchColumn() === false) {
            $this->connection->prepare(
                'INSERT INTO devoluciones_proveedor (id, estado, bodega_id, motivo, nota, transporte_empresa, chofer, placa, cedula_chofer, bultos, completa,
                     usuario_id, created_at, confirmado_por_usuario_id, confirmed_at)
                 VALUES (:id, :estado, :bodega, :motivo, :nota, :empresa, :chofer, :placa, :cedula, :bultos, 0, :usuario, :created_at, :confirmado_por, :confirmed_at)'
            )->execute($datos + [
                ':bodega' => $orden->bodegaId(),
                ':usuario' => $orden->creadoPorUsuarioId(),
                ':created_at' => $orden->createdAt()->format('Y-m-d H:i:s'),
            ]);
            $this->connection->prepare("UPDATE devoluciones_proveedor SET codigo = CONCAT('DEV-', LPAD(secuencia, 6, '0')) WHERE id = ?")
                ->execute([$orden->id()]);
        } else {
            $this->connection->prepare(
                'UPDATE devoluciones_proveedor SET estado = :estado, motivo = :motivo, nota = :nota, transporte_empresa = :empresa, chofer = :chofer,
                     placa = :placa, cedula_chofer = :cedula, bultos = :bultos, confirmado_por_usuario_id = :confirmado_por, confirmed_at = :confirmed_at
                 WHERE id = :id'
            )->execute($datos);
        }

        // Solo se borran las lineas que esta operacion quito (como en las salidas).
        $quitados = $orden->lpnIdsQuitados();
        if ($quitados !== []) {
            $marcas = implode(',', array_fill(0, count($quitados), '?'));
            $this->connection->prepare("DELETE FROM devolucion_proveedor_lpns WHERE devolucion_id = ? AND lpn_id IN ({$marcas})")
                ->execute(array_merge([$orden->id()], $quitados));
        }

        // Un escaneo guardado no se pierde aunque otra lectura de la orden no lo tuviera.
        $linea = $this->connection->prepare(
            'INSERT INTO devolucion_proveedor_lpns (devolucion_id, lpn_id, escaneado_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE escaneado_at = COALESCE(escaneado_at, VALUES(escaneado_at))'
        );
        foreach ($orden->lineas() as $l) {
            $linea->execute([$orden->id(), $l['lpnId'], $l['escaneado']?->format('Y-m-d H:i:s')]);
        }
    }

    public function findById(string $id): ?OrdenDevolucion
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM devoluciones_proveedor WHERE id = ?' . ($this->connection->inTransaction() ? ' FOR UPDATE' : '')
        );
        $stmt->execute([$id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($r === false) {
            return null;
        }

        $lineas = $this->connection->prepare(
            'SELECT dl.lpn_id, dl.escaneado_at, l.codigo, l.carga_id FROM devolucion_proveedor_lpns dl
             JOIN lpns l ON l.id = dl.lpn_id WHERE dl.devolucion_id = ? ORDER BY l.carga_id, l.secuencia'
        );
        $lineas->execute([$id]);

        return OrdenDevolucion::reconstituir(
            (string) $r['id'],
            $r['codigo'] !== null ? (string) $r['codigo'] : null,
            $r['bodega_id'] !== null ? (string) $r['bodega_id'] : null,
            EstadoDevolucion::from((string) $r['estado']),
            (string) $r['motivo'],
            $r['nota'] !== null ? (string) $r['nota'] : null,
            new DatosTransporte($r['transporte_empresa'], $r['chofer'], $r['placa'], $r['cedula_chofer']),
            $r['usuario_id'] !== null ? (string) $r['usuario_id'] : null,
            new DateTimeImmutable((string) $r['created_at']),
            $r['confirmado_por_usuario_id'] !== null ? (string) $r['confirmado_por_usuario_id'] : null,
            $r['confirmed_at'] !== null ? new DateTimeImmutable((string) $r['confirmed_at']) : null,
            array_map(static fn (array $l) => [
                'lpnId' => (string) $l['lpn_id'],
                'codigo' => (string) $l['codigo'],
                'cargaId' => $l['carga_id'] !== null ? (string) $l['carga_id'] : null,
                'escaneado' => $l['escaneado_at'] !== null ? new DateTimeImmutable((string) $l['escaneado_at']) : null,
            ], $lineas->fetchAll(PDO::FETCH_ASSOC)),
        );
    }

    public function listar(array $estados, ?string $bodegaId = null): array
    {
        $where = [];
        $params = [];

        if ($estados !== []) {
            $where[] = 'd.estado IN (' . implode(',', array_fill(0, count($estados), '?')) . ')';
            array_push($params, ...$estados);
        }

        if ($bodegaId !== null) {
            $where[] = 'd.bodega_id = ?';
            $params[] = $bodegaId;
        }

        // Cargas de la orden: las de sus bultos, o la de una devolucion directa vieja (sin lineas).
        $stmt = $this->connection->prepare(
            'SELECT d.id, d.codigo, d.estado, d.bodega_id, d.motivo, d.placa, d.transporte_empresa, d.created_at, d.confirmed_at,
                    COUNT(DISTINCT c.id) AS cargas,
                    GROUP_CONCAT(DISTINCT ' . self::CLIENTE . " ORDER BY cl.nombre SEPARATOR ', ') AS clientes,
                    GROUP_CONCAT(DISTINCT NULLIF(c.proveedor, '') ORDER BY c.proveedor SEPARATOR ', ') AS proveedores,
                    COALESCE(NULLIF(COUNT(dl.lpn_id), 0), MAX(d.bultos)) AS bultos,
                    SUM(dl.escaneado_at IS NOT NULL) AS escaneados
             FROM devoluciones_proveedor d
             LEFT JOIN devolucion_proveedor_lpns dl ON dl.devolucion_id = d.id
             LEFT JOIN lpns l ON l.id = dl.lpn_id
             LEFT JOIN cargas c ON c.id = COALESCE(l.carga_id, d.carga_id)
             LEFT JOIN clientes cl ON cl.id = c.cliente_id"
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '') . '
             GROUP BY d.id
             ORDER BY d.secuencia DESC
             LIMIT 500'
        );
        $stmt->execute($params);

        return array_map(static fn (array $r) => [
            'id' => (string) $r['id'],
            'codigo' => (string) $r['codigo'],
            'estado' => (string) $r['estado'],
            'bodegaId' => $r['bodega_id'] !== null ? (string) $r['bodega_id'] : null,
            'motivo' => (string) $r['motivo'],
            'placa' => $r['placa'] !== null ? (string) $r['placa'] : null,
            'empresa' => $r['transporte_empresa'] !== null ? (string) $r['transporte_empresa'] : null,
            'cargas' => (int) $r['cargas'],
            'clientes' => (string) ($r['clientes'] ?? ''),
            'proveedores' => (string) ($r['proveedores'] ?? ''),
            'bultos' => (int) $r['bultos'],
            'escaneados' => (int) $r['escaneados'],
            'creada' => (string) $r['created_at'],
            'confirmada' => $r['confirmed_at'] !== null ? (string) $r['confirmed_at'] : null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function detalleLineas(string $ordenId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT dl.lpn_id, dl.escaneado_at, l.codigo, l.carga_id, c.tracking_numero, ' . self::CLIENTE . " AS cliente,
                    COALESCE(c.proveedor, '') AS proveedor, COALESCE(c.aduanero_nombre, '') AS aduanero, u.codigo AS ubicacion
             FROM devolucion_proveedor_lpns dl
             JOIN lpns l ON l.id = dl.lpn_id
             LEFT JOIN cargas c ON c.id = l.carga_id
             LEFT JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN ubicaciones u ON u.id = l.ubicacion_id
             WHERE dl.devolucion_id = ?
             ORDER BY c.proveedor, c.tracking_numero, l.secuencia"
        );
        $stmt->execute([$ordenId]);

        return array_map(static fn (array $r) => [
            'lpnId' => (string) $r['lpn_id'],
            'codigo' => (string) $r['codigo'],
            'escaneado' => $r['escaneado_at'] !== null ? (string) $r['escaneado_at'] : null,
            'cargaId' => $r['carga_id'] !== null ? (string) $r['carga_id'] : null,
            'tracking' => (string) ($r['tracking_numero'] ?? '—'),
            'cliente' => (string) $r['cliente'],
            'proveedor' => (string) $r['proveedor'],
            'aduanero' => (string) $r['aduanero'],
            'ubicacion' => $r['ubicacion'] !== null ? (string) $r['ubicacion'] : null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function porCarga(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT d.id, d.codigo, d.estado, COALESCE(d.confirmed_at, d.created_at) AS fecha, d.motivo, u.nombre AS usuario,
                    COALESCE(NULLIF((SELECT COUNT(*) FROM devolucion_proveedor_lpns dl JOIN lpns l ON l.id = dl.lpn_id
                                     WHERE dl.devolucion_id = d.id AND l.carga_id = :c1), 0), d.bultos) AS bultos
             FROM devoluciones_proveedor d
             LEFT JOIN usuarios u ON u.id = COALESCE(d.confirmado_por_usuario_id, d.usuario_id)
             WHERE d.estado <> 'cancelada'
               AND (d.carga_id = :c2 OR EXISTS (SELECT 1 FROM devolucion_proveedor_lpns dl JOIN lpns l ON l.id = dl.lpn_id
                                                 WHERE dl.devolucion_id = d.id AND l.carga_id = :c3))
             ORDER BY d.secuencia DESC"
        );
        $stmt->execute([':c1' => $cargaId, ':c2' => $cargaId, ':c3' => $cargaId]);

        return array_map(fn (array $r) => [
            'id' => (string) $r['id'],
            'codigo' => (string) $r['codigo'],
            'estado' => (string) $r['estado'],
            'fecha' => (string) $r['fecha'],
            'motivo' => (string) $r['motivo'],
            'bultos' => (int) $r['bultos'],
            'usuario' => $r['usuario'] !== null ? (string) $r['usuario'] : null,
            'documentos' => $this->documentos((string) $r['id']),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function documentos(string $ordenId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT d.id, d.nombre, d.ruta, d.created_at, u.nombre AS usuario
             FROM devolucion_proveedor_documentos d LEFT JOIN usuarios u ON u.id = d.usuario_id
             WHERE d.devolucion_id = ? ORDER BY d.created_at'
        );
        $stmt->execute([$ordenId]);

        return array_map(static fn (array $r) => [
            'id' => (string) $r['id'],
            'nombre' => (string) $r['nombre'],
            'ruta' => (string) $r['ruta'],
            'fecha' => (string) $r['created_at'],
            'usuario' => $r['usuario'] !== null ? (string) $r['usuario'] : null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function agregarDocumento(string $ordenId, string $nombre, string $ruta, ?string $usuarioId): void
    {
        $this->connection->prepare(
            'INSERT INTO devolucion_proveedor_documentos (id, devolucion_id, nombre, ruta, usuario_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([(string) Uuid::generate(), $ordenId, mb_substr($nombre, 0, 255), $ruta, $usuarioId]);
    }
}
