<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Persistence;

use Courier\Carga\Domain\Carga;
use Courier\Carga\Domain\CargaRepositoryInterface;
use Courier\Carga\Domain\CargaStatus;
use Courier\Carga\Domain\TipoServicio;
use Courier\Carga\Domain\ValueObject\Aduanero;
use Courier\Carga\Domain\ValueObject\Contenedor;
use Courier\Carga\Domain\ValueObject\DetalleAereo;
use Courier\Carga\Domain\ValueObject\DetalleFreight;
use Courier\Carga\Domain\ValueObject\DetalleMaritimo;
use Courier\Carga\Domain\ValueObject\DetalleServicioInterface;
use Courier\Carga\Domain\ValueObject\LineaCarga;
use Courier\Carga\Domain\ValueObject\TrackingNumero;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PdoCargaRepository implements CargaRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Carga $carga): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO cargas (
                id, tracking_numero, cliente_id, proveedor, proveedor_identificador,
                aduanero_identificador, aduanero_nombre, numero_contenedor, notas,
                factura_proveedor_nombre, factura_proveedor_ruta, tipo_servicio,
                estado, registrado_por_usuario_id, fecha_ingreso, created_at, updated_at
            ) VALUES (
                :id, :tracking_numero, :cliente_id, :proveedor, :proveedor_identificador,
                :aduanero_identificador, :aduanero_nombre, :numero_contenedor, :notas,
                :factura_proveedor_nombre, :factura_proveedor_ruta, :tipo_servicio,
                :estado, :registrado_por_usuario_id, :fecha_ingreso, :created_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE
                cliente_id = VALUES(cliente_id), proveedor = VALUES(proveedor), proveedor_identificador = VALUES(proveedor_identificador),
                aduanero_identificador = VALUES(aduanero_identificador), aduanero_nombre = VALUES(aduanero_nombre),
                numero_contenedor = VALUES(numero_contenedor), notas = VALUES(notas),
                factura_proveedor_nombre = VALUES(factura_proveedor_nombre), factura_proveedor_ruta = VALUES(factura_proveedor_ruta),
                fecha_ingreso = VALUES(fecha_ingreso), estado = VALUES(estado), updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            ':id' => $carga->id(),
            ':tracking_numero' => (string) $carga->trackingNumero(),
            ':cliente_id' => $carga->clienteId(),
            ':proveedor' => $carga->proveedor(),
            ':proveedor_identificador' => $carga->proveedorIdentificador(),
            ':aduanero_identificador' => $carga->aduanero()?->identificador(),
            ':aduanero_nombre' => $carga->aduanero()?->nombre(),
            ':numero_contenedor' => $carga->numeroContenedor() ? (string) $carga->numeroContenedor() : null,
            ':notas' => $carga->notas(),
            ':factura_proveedor_nombre' => $carga->facturaProveedorNombre(),
            ':factura_proveedor_ruta' => $carga->facturaProveedorRuta(),
            ':tipo_servicio' => $carga->tipoServicio()?->value,
            ':estado' => $carga->estado()->value,
            ':registrado_por_usuario_id' => $carga->registradoPorUsuarioId(),
            ':fecha_ingreso' => $carga->fechaIngreso()->format('Y-m-d H:i:s'),
            ':created_at' => $carga->createdAt()->format('Y-m-d H:i:s'),
            ':updated_at' => $carga->updatedAt()->format('Y-m-d H:i:s'),
        ]);

        $this->saveDetalle($carga);
        $this->syncLineas($carga);
    }

    private function syncLineas(Carga $carga): void
    {
        $delete = $this->connection->prepare('DELETE FROM carga_lineas WHERE carga_id = :carga_id');
        $delete->execute([':carga_id' => $carga->id()]);

        $stmt = $this->connection->prepare(
            'INSERT INTO carga_lineas (
                id, carga_id, descripcion, marca, unidad_medida, cantidad, ancho, alto, largo, cubicaje_p3, peso, valor, orden
            ) VALUES (
                :id, :carga_id, :descripcion, :marca, :unidad_medida, :cantidad, :ancho, :alto, :largo, :cubicaje_p3, :peso, :valor, :orden
            )'
        );

        foreach ($carga->lineas() as $orden => $linea) {
            $stmt->execute([
                ':id' => (string) Uuid::generate(),
                ':carga_id' => $carga->id(),
                ':descripcion' => $linea->descripcion(),
                ':marca' => $linea->marca(),
                ':unidad_medida' => $linea->unidadMedida(),
                ':cantidad' => $linea->cantidad(),
                ':ancho' => $linea->ancho(),
                ':alto' => $linea->alto(),
                ':largo' => $linea->largo(),
                ':cubicaje_p3' => $linea->cubicajePies(),
                ':peso' => $linea->peso(),
                ':valor' => $linea->valor(),
                ':orden' => $orden,
            ]);
        }
    }

    private function saveDetalle(Carga $carga): void
    {
        $tipoServicio = $carga->tipoServicio();

        if ($tipoServicio === null) {
            return;
        }

        $detalle = $carga->detalle();

        match ($tipoServicio) {
            TipoServicio::AEREO => $this->saveDetalleAereo($carga->id(), $detalle),
            TipoServicio::MARITIMO => $this->saveDetalleMaritimo($carga->id(), $detalle),
            TipoServicio::FREIGHT => $this->saveDetalleFreight($carga->id(), $detalle),
        };
    }

    private function saveDetalleAereo(string $cargaId, DetalleServicioInterface $detalle): void
    {
        assert($detalle instanceof DetalleAereo);

        $stmt = $this->connection->prepare(
            'INSERT INTO carga_detalle_aereo (carga_id, numero_vuelo, fecha_estimada_llegada, aerolinea)
             VALUES (:carga_id, :numero_vuelo, :fecha, :aerolinea)
             ON DUPLICATE KEY UPDATE
                numero_vuelo = VALUES(numero_vuelo), fecha_estimada_llegada = VALUES(fecha_estimada_llegada),
                aerolinea = VALUES(aerolinea)'
        );

        $stmt->execute([
            ':carga_id' => $cargaId,
            ':numero_vuelo' => $detalle->numeroVuelo(),
            ':fecha' => $detalle->fechaEstimadaLlegada()->format('Y-m-d'),
            ':aerolinea' => $detalle->aerolinea(),
        ]);
    }

    private function saveDetalleMaritimo(string $cargaId, DetalleServicioInterface $detalle): void
    {
        assert($detalle instanceof DetalleMaritimo);

        $stmt = $this->connection->prepare(
            'INSERT INTO carga_detalle_maritimo (carga_id, numero_buque, fecha_estimada_llegada, puerto_origen, puerto_destino)
             VALUES (:carga_id, :numero_buque, :fecha, :origen, :destino)
             ON DUPLICATE KEY UPDATE
                numero_buque = VALUES(numero_buque), fecha_estimada_llegada = VALUES(fecha_estimada_llegada),
                puerto_origen = VALUES(puerto_origen), puerto_destino = VALUES(puerto_destino)'
        );

        $stmt->execute([
            ':carga_id' => $cargaId,
            ':numero_buque' => $detalle->numeroBuque(),
            ':fecha' => $detalle->fechaEstimadaLlegada()->format('Y-m-d'),
            ':origen' => $detalle->puertoOrigen(),
            ':destino' => $detalle->puertoDestino(),
        ]);
    }

    private function saveDetalleFreight(string $cargaId, DetalleServicioInterface $detalle): void
    {
        assert($detalle instanceof DetalleFreight);

        $stmt = $this->connection->prepare(
            'INSERT INTO carga_detalle_freight (carga_id, numero_bl, tipo_contenedor, peso_bruto, peso_neto, fecha_estimada_llegada)
             VALUES (:carga_id, :numero_bl, :tipo_contenedor, :peso_bruto, :peso_neto, :fecha)
             ON DUPLICATE KEY UPDATE
                numero_bl = VALUES(numero_bl), tipo_contenedor = VALUES(tipo_contenedor),
                peso_bruto = VALUES(peso_bruto), peso_neto = VALUES(peso_neto),
                fecha_estimada_llegada = VALUES(fecha_estimada_llegada)'
        );

        $stmt->execute([
            ':carga_id' => $cargaId,
            ':numero_bl' => $detalle->numeroBl(),
            ':tipo_contenedor' => $detalle->tipoContenedor(),
            ':peso_bruto' => $detalle->pesoBruto(),
            ':peso_neto' => $detalle->pesoNeto(),
            ':fecha' => $detalle->fechaEstimadaLlegada()?->format('Y-m-d'),
        ]);
    }

    public function findAll(): array
    {
        $stmt = $this->connection->query('SELECT * FROM cargas ORDER BY created_at DESC');

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findById(string $id): ?Carga
    {
        $stmt = $this->connection->prepare('SELECT * FROM cargas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTrackingNumero(TrackingNumero $numero): ?Carga
    {
        $stmt = $this->connection->prepare('SELECT * FROM cargas WHERE tracking_numero = :numero LIMIT 1');
        $stmt->execute([':numero' => (string) $numero]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByContenedor(Contenedor $contenedor): array
    {
        $stmt = $this->connection->prepare('SELECT * FROM cargas WHERE numero_contenedor = :numero ORDER BY created_at DESC');
        $stmt->execute([':numero' => (string) $contenedor]);

        $resultado = [];

        foreach ($stmt->fetchAll() as $row) {
            $resultado[] = $this->hydrate($row);
        }

        return $resultado;
    }

    public function findByClienteId(string $clienteId): array
    {
        $stmt = $this->connection->prepare('SELECT * FROM cargas WHERE cliente_id = :cliente_id ORDER BY created_at DESC');
        $stmt->execute([':cliente_id' => $clienteId]);

        $resultado = [];

        foreach ($stmt->fetchAll() as $row) {
            $resultado[] = $this->hydrate($row);
        }

        return $resultado;
    }

    public function existsTrackingNumero(TrackingNumero $numero): bool
    {
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM cargas WHERE tracking_numero = :numero');
        $stmt->execute([':numero' => (string) $numero]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** @return array{items: array<int, Carga>, total: int} */
    public function searchPaginado(string $term, ?string $estado, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $term = trim($term);

        $where = '1=1';
        $params = [];

        if ($term !== '') {
            $where .= ' AND (ca.tracking_numero LIKE :term1 OR ca.proveedor LIKE :term2 OR ca.numero_contenedor LIKE :term3 OR c.nombre LIKE :term4)';
            $likeTerm = '%' . $term . '%';
            $params[':term1'] = $likeTerm;
            $params[':term2'] = $likeTerm;
            $params[':term3'] = $likeTerm;
            $params[':term4'] = $likeTerm;
        }

        if ($estado !== null && $estado !== '') {
            $where .= ' AND ca.estado = :estado';
            $params[':estado'] = $estado;
        }

        $countStmt = $this->connection->prepare(
            "SELECT COUNT(*) FROM cargas ca JOIN clientes c ON c.id = ca.cliente_id WHERE {$where}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->connection->prepare(
            "SELECT ca.* FROM cargas ca JOIN clientes c ON c.id = ca.cliente_id WHERE {$where}
             ORDER BY ca.created_at DESC LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());

        return ['items' => $items, 'total' => $total];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Carga
    {
        $tipoServicio = $row['tipo_servicio'] !== null ? TipoServicio::from((string) $row['tipo_servicio']) : null;
        $detalle = $tipoServicio !== null ? $this->loadDetalle($tipoServicio, (string) $row['id']) : null;
        $aduanero = $row['aduanero_identificador'] !== null && $row['aduanero_nombre'] !== null
            ? new Aduanero((string) $row['aduanero_identificador'], (string) $row['aduanero_nombre'])
            : null;

        return Carga::reconstituir(
            new Uuid((string) $row['id']),
            new TrackingNumero((string) $row['tracking_numero']),
            (string) $row['cliente_id'],
            (string) $row['proveedor'],
            $row['proveedor_identificador'] !== null ? (string) $row['proveedor_identificador'] : null,
            $aduanero,
            $row['numero_contenedor'] !== null ? new Contenedor((string) $row['numero_contenedor']) : null,
            $tipoServicio,
            $detalle,
            CargaStatus::from((string) $row['estado']),
            (string) $row['registrado_por_usuario_id'],
            new DateTimeImmutable((string) $row['fecha_ingreso']),
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
            [],
            $this->hydrarLineas((string) $row['id']),
            $row['notas'] !== null ? (string) $row['notas'] : null,
            $row['factura_proveedor_nombre'] !== null ? (string) $row['factura_proveedor_nombre'] : null,
            $row['factura_proveedor_ruta'] !== null ? (string) $row['factura_proveedor_ruta'] : null,
        );
    }

    /** @return array<int, LineaCarga> */
    private function hydrarLineas(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT descripcion, marca, unidad_medida, cantidad, ancho, alto, largo, peso, valor, cubicaje_p3 FROM carga_lineas
             WHERE carga_id = :carga_id ORDER BY orden ASC'
        );
        $stmt->execute([':carga_id' => $cargaId]);

        return array_map(
            fn (array $row) => new LineaCarga(
                (string) $row['descripcion'],
                (string) $row['unidad_medida'],
                (float) $row['cantidad'],
                (float) $row['ancho'],
                (float) $row['alto'],
                (float) $row['largo'],
                (float) $row['peso'],
                (float) $row['valor'],
                $row['marca'] !== null ? (string) $row['marca'] : null,
                $row['cubicaje_p3'] !== null ? (float) $row['cubicaje_p3'] : null,
            ),
            $stmt->fetchAll(),
        );
    }

    private function loadDetalle(TipoServicio $tipo, string $cargaId): DetalleServicioInterface
    {
        $tabla = match ($tipo) {
            TipoServicio::AEREO => 'carga_detalle_aereo',
            TipoServicio::MARITIMO => 'carga_detalle_maritimo',
            TipoServicio::FREIGHT => 'carga_detalle_freight',
        };

        $stmt = $this->connection->prepare("SELECT * FROM {$tabla} WHERE carga_id = :carga_id LIMIT 1");
        $stmt->execute([':carga_id' => $cargaId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException("No se encontro el detalle de servicio para la carga {$cargaId}.");
        }

        return match ($tipo) {
            TipoServicio::AEREO => new DetalleAereo(
                (string) $row['numero_vuelo'],
                new DateTimeImmutable((string) $row['fecha_estimada_llegada']),
                (string) $row['aerolinea'],
            ),
            TipoServicio::MARITIMO => new DetalleMaritimo(
                (string) $row['numero_buque'],
                new DateTimeImmutable((string) $row['fecha_estimada_llegada']),
                (string) $row['puerto_origen'],
                (string) $row['puerto_destino'],
            ),
            TipoServicio::FREIGHT => new DetalleFreight(
                (string) $row['numero_bl'],
                (string) $row['tipo_contenedor'],
                (float) $row['peso_bruto'],
                (float) $row['peso_neto'],
                $row['fecha_estimada_llegada'] !== null ? new DateTimeImmutable((string) $row['fecha_estimada_llegada']) : null,
            ),
        };
    }
}
