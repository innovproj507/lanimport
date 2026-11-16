<?php

declare(strict_types=1);

namespace Courier\Traslado\Infrastructure\Persistence;

use Courier\Shared\Domain\BodegaActivaInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Traslado\Domain\EstadoTraslado;
use Courier\Traslado\Domain\Traslado;
use Courier\Traslado\Domain\TrasladoRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class PdoTrasladoRepository implements TrasladoRepositoryInterface
{
    public function __construct(
        private readonly PDO $connection,
        private readonly ?BodegaActivaInterface $bodegaActiva = null,
    ) {
    }

    public function save(Traslado $t): void
    {
        $existe = $this->connection->prepare('SELECT COUNT(*) FROM traslados WHERE id = :id');
        $existe->execute([':id' => $t->id()]);

        if ((int) $existe->fetchColumn() === 0) {
            $this->connection->prepare(
                'INSERT INTO traslados (id, origen_bodega_id, destino_bodega_id, estado, nota, creado_por_usuario_id, created_at)
                 VALUES (:id, :origen, :destino, :estado, :nota, :usuario, :created_at)'
            )->execute([
                ':id' => $t->id(),
                ':origen' => $t->origenBodegaId(),
                ':destino' => $t->destinoBodegaId(),
                ':estado' => $t->estado()->value,
                ':nota' => $t->nota(),
                ':usuario' => $t->creadoPorUsuarioId(),
                ':created_at' => $t->createdAt()->format('Y-m-d H:i:s'),
            ]);
            $secuencia = (int) $this->connection->lastInsertId();
            $this->connection->prepare('UPDATE traslados SET codigo = :codigo WHERE id = :id')
                ->execute([':codigo' => 'TR-' . str_pad((string) $secuencia, 6, '0', STR_PAD_LEFT), ':id' => $t->id()]);
        } else {
            $this->connection->prepare(
                'UPDATE traslados SET estado = :estado, despachado_por_usuario_id = :despachado_por, despachado_at = :despachado_at,
                     recibido_at = :recibido_at WHERE id = :id'
            )->execute([
                ':estado' => $t->estado()->value,
                ':despachado_por' => $t->despachadoPorUsuarioId(),
                ':despachado_at' => $t->despachadoAt()?->format('Y-m-d H:i:s'),
                ':recibido_at' => $t->recibidoAt()?->format('Y-m-d H:i:s'),
                ':id' => $t->id(),
            ]);
        }

        // Lineas: las que ya no estan (no escaneadas al despachar) se borran; el resto se actualiza.
        $ids = $t->lpnIds();
        $borrar = 'DELETE FROM traslado_lineas WHERE traslado_id = ?' . ($ids !== [] ? ' AND lpn_id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')' : '');
        $this->connection->prepare($borrar)->execute([$t->id(), ...$ids]);

        $linea = $this->connection->prepare(
            'INSERT INTO traslado_lineas (id, traslado_id, lpn_id, salida_escaneado_at, llegada_escaneado_at)
             VALUES (:id, :traslado, :lpn, :salida, :llegada)
             ON DUPLICATE KEY UPDATE salida_escaneado_at = VALUES(salida_escaneado_at), llegada_escaneado_at = VALUES(llegada_escaneado_at)'
        );
        foreach ($t->lineas() as $l) {
            $linea->execute([
                ':id' => (string) Uuid::generate(),
                ':traslado' => $t->id(),
                ':lpn' => $l['lpnId'],
                ':salida' => $l['salida']?->format('Y-m-d H:i:s'),
                ':llegada' => $l['llegada']?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function findById(string $id): ?Traslado
    {
        $stmt = $this->connection->prepare('SELECT * FROM traslados WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function listar(array $estados = []): array
    {
        $where = ['1=1'];
        $params = [];

        if ($estados !== []) {
            $marcas = [];
            foreach (array_values($estados) as $i => $estado) {
                $marcas[] = ":e{$i}";
                $params[":e{$i}"] = $estado;
            }
            $where[] = 'estado IN (' . implode(',', $marcas) . ')';
        }

        if (($bodega = $this->bodegaActiva?->id()) !== null) {
            $where[] = '(origen_bodega_id = :b1 OR destino_bodega_id = :b2)';
            $params[':b1'] = $bodega;
            $params[':b2'] = $bodega;
        }

        $stmt = $this->connection->prepare('SELECT * FROM traslados WHERE ' . implode(' AND ', $where) . ' ORDER BY secuencia DESC LIMIT 200');
        $stmt->execute($params);

        return array_map(fn (array $r) => $this->hydrate($r), $stmt->fetchAll());
    }

    public function detalleLineas(string $trasladoId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT l.codigo, c.id AS carga_id, COALESCE(c.tracking_numero, 'Caja reempacada') AS tracking,
                    TRIM(CONCAT(COALESCE(cl.nombre, ''), ' ', COALESCE(cl.apellido, ''))) AS cliente,
                    tl.salida_escaneado_at, tl.llegada_escaneado_at
             FROM traslado_lineas tl
             JOIN lpns l ON l.id = tl.lpn_id
             LEFT JOIN cargas c ON c.id = l.carga_id
             LEFT JOIN clientes cl ON cl.id = l.cliente_id
             WHERE tl.traslado_id = :id
             ORDER BY tracking, l.orden_en_carga, l.codigo"
        );
        $stmt->execute([':id' => $trasladoId]);

        return array_map(static fn (array $r) => [
            'codigo' => (string) $r['codigo'],
            'cargaId' => $r['carga_id'] !== null ? (string) $r['carga_id'] : null,
            'tracking' => (string) $r['tracking'],
            'cliente' => (string) $r['cliente'],
            'salida' => $r['salida_escaneado_at'],
            'llegada' => $r['llegada_escaneado_at'],
        ], $stmt->fetchAll());
    }

    public function enCaminoHacia(string $bodegaId): int
    {
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM traslados WHERE estado = 'en_camino' AND destino_bodega_id = :b");
        $stmt->execute([':b' => $bodegaId]);

        return (int) $stmt->fetchColumn();
    }

    public function abiertoConLpn(string $lpnId): ?Traslado
    {
        $stmt = $this->connection->prepare(
            "SELECT t.* FROM traslados t JOIN traslado_lineas tl ON tl.traslado_id = t.id
             WHERE tl.lpn_id = :lpn AND t.estado IN ('pendiente', 'en_camino') LIMIT 1"
        );
        $stmt->execute([':lpn' => $lpnId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $r */
    private function hydrate(array $r): Traslado
    {
        $stmt = $this->connection->prepare(
            'SELECT tl.lpn_id, l.codigo, tl.salida_escaneado_at, tl.llegada_escaneado_at
             FROM traslado_lineas tl JOIN lpns l ON l.id = tl.lpn_id
             WHERE tl.traslado_id = :id ORDER BY l.carga_id, l.orden_en_carga, l.codigo'
        );
        $stmt->execute([':id' => $r['id']]);
        $fecha = static fn ($v) => $v !== null ? new DateTimeImmutable((string) $v) : null;

        return Traslado::reconstituir(
            (string) $r['id'],
            (string) $r['origen_bodega_id'],
            (string) $r['destino_bodega_id'],
            EstadoTraslado::from((string) $r['estado']),
            (string) $r['creado_por_usuario_id'],
            new DateTimeImmutable((string) $r['created_at']),
            $r['codigo'] !== null ? (string) $r['codigo'] : null,
            $r['nota'] !== null ? (string) $r['nota'] : null,
            $r['despachado_por_usuario_id'] !== null ? (string) $r['despachado_por_usuario_id'] : null,
            $fecha($r['despachado_at']),
            $fecha($r['recibido_at']),
            array_map(static fn (array $l) => [
                'lpnId' => (string) $l['lpn_id'],
                'codigo' => (string) $l['codigo'],
                'salida' => $fecha($l['salida_escaneado_at']),
                'llegada' => $fecha($l['llegada_escaneado_at']),
            ], $stmt->fetchAll()),
        );
    }
}
