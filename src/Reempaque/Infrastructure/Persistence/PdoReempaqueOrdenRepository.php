<?php

declare(strict_types=1);

namespace Courier\Reempaque\Infrastructure\Persistence;

use Courier\Reempaque\Domain\EstadoReempaqueOrden;
use Courier\Reempaque\Domain\ReempaqueOrden;
use Courier\Reempaque\Domain\ReempaqueOrdenRepositoryInterface;
use Courier\Reempaque\Domain\TipoReempaque;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

/**
 * Orden de reempaque: cabecera en reempaque_ordenes, bultos origen y cajas destino en
 * reempaque_lpns (cerrada_at marca la caja cerrada) y lo que lleva cada caja en lpn_genealogia.
 */
final class PdoReempaqueOrdenRepository implements ReempaqueOrdenRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(ReempaqueOrden $orden): void
    {
        if ($this->findHeader($orden->id()) === null) {
            $this->insert($orden);
        } else {
            $this->update($orden);
        }

        $this->guardarCajas($orden);
    }

    private function insert(ReempaqueOrden $orden): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO reempaque_ordenes (
                id, tipo, cliente_id, estado, instrucciones, creado_por_usuario_id, created_at, completed_at
            ) VALUES (
                :id, :tipo, :cliente_id, :estado, :instrucciones, :creado_por_usuario_id, :created_at, :completed_at
            )'
        );

        $stmt->execute([
            ':id' => $orden->id(),
            ':tipo' => $orden->tipo()->value,
            ':cliente_id' => $orden->clienteId(),
            ':estado' => $orden->estado()->value,
            ':instrucciones' => $orden->instrucciones(),
            ':creado_por_usuario_id' => $orden->creadoPorUsuarioId(),
            ':created_at' => $orden->createdAt()->format('Y-m-d H:i:s'),
            ':completed_at' => $orden->completedAt()?->format('Y-m-d H:i:s'),
        ]);

        $secuencia = (int) $this->connection->lastInsertId();
        $codigo = 'REP-' . str_pad((string) $secuencia, 6, '0', STR_PAD_LEFT);

        $update = $this->connection->prepare('UPDATE reempaque_ordenes SET codigo = :codigo WHERE id = :id');
        $update->execute([':codigo' => $codigo, ':id' => $orden->id()]);

        $origen = $this->connection->prepare(
            "INSERT IGNORE INTO reempaque_lpns (id, reempaque_orden_id, lpn_id, rol) VALUES (:id, :orden, :lpn, 'origen')"
        );

        foreach ($orden->origenLpnIds() as $lpnId) {
            $origen->execute([':id' => (string) Uuid::generate(), ':orden' => $orden->id(), ':lpn' => $lpnId]);
        }
    }

    private function update(ReempaqueOrden $orden): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE reempaque_ordenes SET estado = :estado, completed_at = :completed_at WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $orden->id(),
            ':estado' => $orden->estado()->value,
            ':completed_at' => $orden->completedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    /** Sincroniza las cajas (destino) y su contenido (genealogia) con el estado de la orden. */
    private function guardarCajas(ReempaqueOrden $orden): void
    {
        $cajas = $orden->cajas();

        // Contenido: se reescribe completo (las cajas abiertas cambian a cada escaneo).
        $this->connection->prepare('DELETE FROM lpn_genealogia WHERE reempaque_orden_id = :orden')
            ->execute([':orden' => $orden->id()]);

        // Cajas que ya no estan (descartadas o la orden se cancelo).
        $actuales = array_keys($cajas);
        $borrar = $this->connection->prepare("SELECT lpn_id FROM reempaque_lpns WHERE reempaque_orden_id = :orden AND rol = 'destino'");
        $borrar->execute([':orden' => $orden->id()]);

        foreach ($borrar->fetchAll(PDO::FETCH_COLUMN) as $lpnId) {
            if (!in_array($lpnId, $actuales, true)) {
                $this->connection->prepare("DELETE FROM reempaque_lpns WHERE reempaque_orden_id = :orden AND lpn_id = :lpn AND rol = 'destino'")
                    ->execute([':orden' => $orden->id(), ':lpn' => $lpnId]);
            }
        }

        $caja = $this->connection->prepare(
            "INSERT INTO reempaque_lpns (id, reempaque_orden_id, lpn_id, rol, cerrada_at)
             VALUES (:id, :orden, :lpn, 'destino', :cerrada)
             ON DUPLICATE KEY UPDATE cerrada_at = COALESCE(reempaque_lpns.cerrada_at, VALUES(cerrada_at))"
        );
        $contenido = $this->connection->prepare(
            'INSERT INTO lpn_genealogia (id, lpn_origen_id, lpn_destino_id, reempaque_orden_id, created_at)
             VALUES (:id, :origen, :destino, :orden, :fecha)'
        );
        $ahora = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($cajas as $destinoId => $datos) {
            $caja->execute([
                ':id' => (string) Uuid::generate(),
                ':orden' => $orden->id(),
                ':lpn' => $destinoId,
                ':cerrada' => $datos['cerrada'] ? $ahora : null,
            ]);

            foreach ($datos['contenido'] as $origenId) {
                $contenido->execute([
                    ':id' => (string) Uuid::generate(),
                    ':origen' => $origenId,
                    ':destino' => $destinoId,
                    ':orden' => $orden->id(),
                    ':fecha' => $ahora,
                ]);
            }
        }
    }

    public function findById(string $id): ?ReempaqueOrden
    {
        $row = $this->findHeader($id);

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function search(array $filtros): array
    {
        $where = [];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[] = 'estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['clienteId'])) {
            $where[] = 'cliente_id = :cliente_id';
            $params[':cliente_id'] = $filtros['clienteId'];
        }

        $sql = 'SELECT * FROM reempaque_ordenes';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY secuencia DESC';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    /** @return array<string, mixed>|null */
    private function findHeader(string $id): ?array
    {
        $stmt = $this->connection->prepare('SELECT * FROM reempaque_ordenes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ReempaqueOrden
    {
        $ordenId = (string) $row['id'];

        $origenes = $this->connection->prepare(
            "SELECT rl.lpn_id FROM reempaque_lpns rl JOIN lpns l ON l.id = rl.lpn_id
             WHERE rl.reempaque_orden_id = :orden AND rl.rol = 'origen'
             ORDER BY l.carga_id, l.orden_en_carga, l.codigo"
        );
        $origenes->execute([':orden' => $ordenId]);

        // Cajas en el orden en que se abrieron (secuencia del LPN).
        $destinos = $this->connection->prepare(
            "SELECT rl.lpn_id, rl.cerrada_at FROM reempaque_lpns rl JOIN lpns l ON l.id = rl.lpn_id
             WHERE rl.reempaque_orden_id = :orden AND rl.rol = 'destino'
             ORDER BY l.secuencia"
        );
        $destinos->execute([':orden' => $ordenId]);

        $cajas = [];
        foreach ($destinos->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $cajas[(string) $d['lpn_id']] = ['cerrada' => $d['cerrada_at'] !== null, 'contenido' => []];
        }

        $contenido = $this->connection->prepare(
            'SELECT lpn_origen_id, lpn_destino_id FROM lpn_genealogia WHERE reempaque_orden_id = :orden ORDER BY created_at, id'
        );
        $contenido->execute([':orden' => $ordenId]);

        foreach ($contenido->fetchAll(PDO::FETCH_ASSOC) as $g) {
            if (isset($cajas[$g['lpn_destino_id']])) {
                $cajas[$g['lpn_destino_id']]['contenido'][] = (string) $g['lpn_origen_id'];
            }
        }

        return new ReempaqueOrden(
            new Uuid($ordenId),
            TipoReempaque::from((string) $row['tipo']),
            (string) $row['cliente_id'],
            EstadoReempaqueOrden::from((string) $row['estado']),
            array_map('strval', $origenes->fetchAll(PDO::FETCH_COLUMN)),
            $cajas,
            (string) $row['creado_por_usuario_id'],
            new DateTimeImmutable((string) $row['created_at']),
            $row['completed_at'] !== null ? new DateTimeImmutable((string) $row['completed_at']) : null,
            $row['codigo'] !== null ? (string) $row['codigo'] : null,
            $row['secuencia'] !== null ? (int) $row['secuencia'] : null,
            isset($row['instrucciones']) ? (string) $row['instrucciones'] : null,
        );
    }
}
