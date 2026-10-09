<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Persistence;

use Courier\Carga\Domain\ActaRecepcion;
use Courier\Carga\Domain\ActaRecepcionRepositoryInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoActaRecepcionRepository implements ActaRecepcionRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(ActaRecepcion $acta): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO cargas_actas_recepcion (
                id, carga_id, cliente_id, marca, empresa_transporte, chofer_nombre, placa, cantidad_bultos, cantidad_rollos,
                total_recibido, tipos_mercancia, descripcion_mercancia, entregado_por, entregado_por_cedula,
                verificado_por_usuario_id, fecha_recepcion, actualizado_por_usuario_id, created_at, updated_at
             ) VALUES (
                :id, :carga_id, :cliente_id, :marca, :empresa_transporte, :chofer_nombre, :placa, :cantidad_bultos, :cantidad_rollos,
                :total_recibido, :tipos_mercancia, :descripcion_mercancia, :entregado_por, :entregado_por_cedula,
                :verificado_por_usuario_id, :fecha_recepcion, :actualizado_por_usuario_id, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                carga_id = VALUES(carga_id),
                cliente_id = VALUES(cliente_id),
                marca = VALUES(marca),
                empresa_transporte = VALUES(empresa_transporte),
                chofer_nombre = VALUES(chofer_nombre),
                placa = VALUES(placa),
                cantidad_bultos = VALUES(cantidad_bultos),
                cantidad_rollos = VALUES(cantidad_rollos),
                total_recibido = VALUES(total_recibido),
                tipos_mercancia = VALUES(tipos_mercancia),
                descripcion_mercancia = VALUES(descripcion_mercancia),
                entregado_por = VALUES(entregado_por),
                entregado_por_cedula = VALUES(entregado_por_cedula),
                actualizado_por_usuario_id = VALUES(actualizado_por_usuario_id),
                updated_at = VALUES(updated_at)'
        );

        $tipos = $acta->tiposMercancia();

        $stmt->execute([
            ':id' => $acta->id(),
            ':carga_id' => $acta->cargaId(),
            ':cliente_id' => $acta->clienteId(),
            ':marca' => $acta->marca(),
            ':empresa_transporte' => $acta->empresaTransporte(),
            ':chofer_nombre' => $acta->choferNombre(),
            ':placa' => $acta->placa(),
            ':cantidad_bultos' => $acta->cantidadBultos(),
            ':cantidad_rollos' => $acta->cantidadRollos(),
            ':total_recibido' => $acta->totalRecibido(),
            ':tipos_mercancia' => $tipos !== [] ? implode(',', $tipos) : null,
            ':descripcion_mercancia' => $acta->descripcionMercancia(),
            ':entregado_por' => $acta->entregadoPor(),
            ':entregado_por_cedula' => $acta->entregadoPorCedula(),
            ':verificado_por_usuario_id' => $acta->verificadoPorUsuarioId(),
            ':fecha_recepcion' => $acta->fechaRecepcion()->format('Y-m-d H:i:s'),
            ':actualizado_por_usuario_id' => $acta->actualizadoPorUsuarioId(),
            ':created_at' => $acta->createdAt()->format('Y-m-d H:i:s'),
            ':updated_at' => $acta->updatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?ActaRecepcion
    {
        $stmt = $this->connection->prepare('SELECT * FROM cargas_actas_recepcion WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCargaId(string $cargaId): ?ActaRecepcion
    {
        $stmt = $this->connection->prepare('SELECT * FROM cargas_actas_recepcion WHERE carga_id = :carga_id LIMIT 1');
        $stmt->execute([':carga_id' => $cargaId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function cargasPendientes(): array
    {
        $stmt = $this->connection->query(
            "SELECT ca.id, ca.cliente_id, ca.tracking_numero, c.nombre AS cliente_nombre, ca.proveedor, ca.fecha_ingreso
             FROM cargas ca
             JOIN clientes c ON c.id = ca.cliente_id
             LEFT JOIN cargas_actas_recepcion a ON a.carga_id = ca.id
             WHERE a.id IS NULL AND ca.estado <> 'entregado'
             ORDER BY ca.fecha_ingreso ASC"
        );

        return array_map(
            static fn (array $row) => [
                'cargaId' => (string) $row['id'],
                'clienteId' => (string) $row['cliente_id'],
                'trackingNumero' => (string) $row['tracking_numero'],
                'clienteNombre' => (string) $row['cliente_nombre'],
                'proveedor' => (string) $row['proveedor'],
                'fechaIngreso' => (string) $row['fecha_ingreso'],
            ],
            $stmt->fetchAll(),
        );
    }

    public function actasSinCarga(): array
    {
        $stmt = $this->connection->query(
            'SELECT a.id, a.numero, a.cliente_id, c.nombre AS cliente_nombre, a.marca, a.total_recibido, a.entregado_por, a.fecha_recepcion
             FROM cargas_actas_recepcion a
             JOIN clientes c ON c.id = a.cliente_id
             WHERE a.carga_id IS NULL
             ORDER BY a.fecha_recepcion ASC'
        );

        return array_map(
            fn (array $row) => [
                'actaId' => (string) $row['id'],
                'codigo' => $this->codigo((int) $row['numero']),
                'clienteId' => (string) $row['cliente_id'],
                'clienteNombre' => (string) $row['cliente_nombre'],
                'marca' => $row['marca'] !== null ? (string) $row['marca'] : null,
                'totalRecibido' => (int) $row['total_recibido'],
                'entregadoPor' => (string) $row['entregado_por'],
                'fechaRecepcion' => (string) $row['fecha_recepcion'],
            ],
            $stmt->fetchAll(),
        );
    }

    public function actasRecientes(int $limite = 20): array
    {
        $stmt = $this->connection->query(
            'SELECT a.id, a.numero, ca.tracking_numero, c.nombre AS cliente_nombre, a.total_recibido, a.entregado_por,
                    u.nombre AS verificado_por, a.fecha_recepcion
             FROM cargas_actas_recepcion a
             LEFT JOIN cargas ca ON ca.id = a.carga_id
             JOIN clientes c ON c.id = a.cliente_id
             JOIN usuarios u ON u.id = a.verificado_por_usuario_id
             ORDER BY a.fecha_recepcion DESC
             LIMIT ' . max(1, $limite)
        );

        return array_map(
            fn (array $row) => [
                'actaId' => (string) $row['id'],
                'codigo' => $this->codigo((int) $row['numero']),
                'trackingNumero' => $row['tracking_numero'] !== null ? (string) $row['tracking_numero'] : null,
                'clienteNombre' => (string) $row['cliente_nombre'],
                'totalRecibido' => (int) $row['total_recibido'],
                'entregadoPor' => (string) $row['entregado_por'],
                'verificadoPor' => (string) $row['verificado_por'],
                'fechaRecepcion' => (string) $row['fecha_recepcion'],
            ],
            $stmt->fetchAll(),
        );
    }

    private function codigo(int $numero): string
    {
        return 'REC-' . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ActaRecepcion
    {
        $tipos = (string) ($row['tipos_mercancia'] ?? '');

        return new ActaRecepcion(
            new Uuid((string) $row['id']),
            (int) $row['numero'],
            $row['carga_id'] !== null ? (string) $row['carga_id'] : null,
            (string) $row['cliente_id'],
            $row['marca'] !== null ? (string) $row['marca'] : null,
            $row['empresa_transporte'] !== null ? (string) $row['empresa_transporte'] : null,
            $row['chofer_nombre'] !== null ? (string) $row['chofer_nombre'] : null,
            $row['placa'] !== null ? (string) $row['placa'] : null,
            (int) $row['cantidad_bultos'],
            (int) $row['cantidad_rollos'],
            (int) $row['total_recibido'],
            $tipos !== '' ? explode(',', $tipos) : [],
            $row['descripcion_mercancia'] !== null ? (string) $row['descripcion_mercancia'] : null,
            (string) $row['entregado_por'],
            (string) $row['entregado_por_cedula'],
            (string) $row['verificado_por_usuario_id'],
            new DateTimeImmutable((string) $row['fecha_recepcion']),
            $row['actualizado_por_usuario_id'] !== null ? (string) $row['actualizado_por_usuario_id'] : null,
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
        );
    }
}
