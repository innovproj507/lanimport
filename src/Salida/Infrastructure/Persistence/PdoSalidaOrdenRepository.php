<?php

declare(strict_types=1);

namespace Courier\Salida\Infrastructure\Persistence;

use Courier\Salida\Domain\EstadoSalidaOrden;
use Courier\Salida\Domain\SalidaLinea;
use Courier\Salida\Domain\SalidaOrden;
use Courier\Salida\Domain\SalidaOrdenRepositoryInterface;
use Courier\Salida\Domain\ValueObject\DatosEmbarque;
use Courier\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PDO;

final class PdoSalidaOrdenRepository implements SalidaOrdenRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(SalidaOrden $orden): void
    {
        if ($this->findHeader($orden->id()) === null) {
            $this->insert($orden);

            return;
        }

        $this->update($orden);
    }

    private function insert(SalidaOrden $orden): void
    {
        // Consecutivo DO del ano: se bloquean las filas del ano para que dos salidas
        // creadas al mismo tiempo no tomen el mismo numero (corre dentro del UnitOfWork).
        $anio = (int) $orden->createdAt()->format('Y');
        $siguiente = $this->connection->prepare(
            'SELECT COALESCE(MAX(do_numero), 0) + 1 FROM salida_ordenes WHERE do_anio = :anio FOR UPDATE'
        );
        $siguiente->execute([':anio' => $anio]);
        $doNumero = (int) $siguiente->fetchColumn();

        $stmt = $this->connection->prepare(
            'INSERT INTO salida_ordenes (
                id, do_anio, do_numero, cliente_id, aduanero_identificador, estado, creado_por_usuario_id, created_at, confirmed_at,
                numero_contenedor, numero_sello, naviera_id, nombre_barco, fecha_cargue, fecha_salida_etd, destino_final
            ) VALUES (
                :id, :do_anio, :do_numero, :cliente_id, :aduanero_identificador, :estado, :creado_por_usuario_id, :created_at, :confirmed_at,
                :numero_contenedor, :numero_sello, :naviera_id, :nombre_barco, :fecha_cargue, :fecha_salida_etd, :destino_final
            )'
        );

        $stmt->execute([
            ':id' => $orden->id(),
            ':do_anio' => $anio,
            ':do_numero' => $doNumero,
            ':cliente_id' => $orden->clienteId(),
            ':aduanero_identificador' => $orden->aduaneroIdentificador(),
            ':estado' => $orden->estado()->value,
            ':creado_por_usuario_id' => $orden->creadoPorUsuarioId(),
            ':created_at' => $orden->createdAt()->format('Y-m-d H:i:s'),
            ':confirmed_at' => $orden->confirmedAt()?->format('Y-m-d H:i:s'),
        ] + $this->parametrosEmbarque($orden));

        $secuencia = (int) $this->connection->lastInsertId();
        $codigo = 'SAL-' . str_pad((string) $secuencia, 6, '0', STR_PAD_LEFT);

        $update = $this->connection->prepare('UPDATE salida_ordenes SET codigo = :codigo WHERE id = :id');
        $update->execute([':codigo' => $codigo, ':id' => $orden->id()]);

        $insertLinea = $this->connection->prepare(
            'INSERT INTO salida_lineas (id, salida_orden_id, lpn_id, escaneado, escaneado_at)
             VALUES (:id, :salida_orden_id, :lpn_id, :escaneado, :escaneado_at)'
        );

        foreach ($orden->lineas() as $linea) {
            $insertLinea->execute([
                ':id' => (string) Uuid::generate(),
                ':salida_orden_id' => $orden->id(),
                ':lpn_id' => $linea->lpnId(),
                ':escaneado' => $linea->escaneado() ? 1 : 0,
                ':escaneado_at' => $linea->escaneadoAt()?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function update(SalidaOrden $orden): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE salida_ordenes SET estado = :estado, confirmed_at = :confirmed_at, cliente_id = :cliente_id,
                numero_contenedor = :numero_contenedor, numero_sello = :numero_sello, naviera_id = :naviera_id,
                nombre_barco = :nombre_barco, fecha_cargue = :fecha_cargue, fecha_salida_etd = :fecha_salida_etd,
                destino_final = :destino_final
             WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $orden->id(),
            ':estado' => $orden->estado()->value,
            ':confirmed_at' => $orden->confirmedAt()?->format('Y-m-d H:i:s'),
            ':cliente_id' => $orden->clienteId(),
        ] + $this->parametrosEmbarque($orden));

        // Lineas nuevas (cargas agregadas a una salida pendiente) se insertan; las demas se actualizan.
        $upsertLinea = $this->connection->prepare(
            'INSERT INTO salida_lineas (id, salida_orden_id, lpn_id, escaneado, escaneado_at)
             VALUES (:id, :salida_orden_id, :lpn_id, :escaneado, :escaneado_at)
             ON DUPLICATE KEY UPDATE escaneado = VALUES(escaneado), escaneado_at = VALUES(escaneado_at)'
        );

        foreach ($orden->lineas() as $linea) {
            $upsertLinea->execute([
                ':id' => (string) Uuid::generate(),
                ':escaneado' => $linea->escaneado() ? 1 : 0,
                ':escaneado_at' => $linea->escaneadoAt()?->format('Y-m-d H:i:s'),
                ':salida_orden_id' => $orden->id(),
                ':lpn_id' => $linea->lpnId(),
            ]);
        }
    }

    public function findById(string $id): ?SalidaOrden
    {
        $row = $this->findHeader($id);

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row, $this->hydrarLineas($id));
    }

    public function clientesPorOrden(array $ordenIds): array
    {
        if ($ordenIds === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ordenIds), '?'));
        $stmt = $this->connection->prepare(
            "SELECT DISTINCT sl.salida_orden_id, TRIM(CONCAT(c.nombre, ' ', COALESCE(c.apellido, ''))) AS nombre
             FROM salida_lineas sl
             JOIN lpns l ON l.id = sl.lpn_id
             JOIN clientes c ON c.id = l.cliente_id
             WHERE sl.salida_orden_id IN ({$marcas})
             ORDER BY nombre"
        );
        $stmt->execute(array_values($ordenIds));

        $resultado = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $resultado[(string) $fila['salida_orden_id']][] = (string) $fila['nombre'];
        }

        return $resultado;
    }

    public function avancePorCarga(string $ordenId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT ca.id AS carga_id, ca.tracking_numero, TRIM(CONCAT(c.nombre, ' ', COALESCE(c.apellido, ''))) AS cliente,
                    l.id AS lpn_id, l.marca, l.codigo, sl.escaneado
             FROM salida_lineas sl
             JOIN lpns l ON l.id = sl.lpn_id
             LEFT JOIN cargas ca ON ca.id = l.carga_id
             LEFT JOIN clientes c ON c.id = l.cliente_id
             WHERE sl.salida_orden_id = :orden
             ORDER BY ca.tracking_numero, l.orden_en_carga, l.codigo"
        );
        $stmt->execute([':orden' => $ordenId]);

        $porCarga = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            // Una caja reempacada no tiene carga propia: va como grupo propio.
            $clave = $fila['carga_id'] !== null ? (string) $fila['carga_id'] : 'caja-' . $fila['lpn_id'];
            $porCarga[$clave] ??= [
                'cargaId' => $clave,
                'tracking' => $fila['tracking_numero'] !== null ? (string) $fila['tracking_numero'] : 'Caja reempacada',
                'cliente' => (string) ($fila['cliente'] ?? ''),
                'marca' => $fila['marca'] !== null ? (string) $fila['marca'] : null,
                'total' => 0,
                'escaneadas' => 0,
                'pendientes' => [],
                'codigos' => [],
            ];
            $porCarga[$clave]['total']++;
            $porCarga[$clave]['codigos'][] = (string) $fila['codigo'];

            if ((bool) $fila['escaneado']) {
                $porCarga[$clave]['escaneadas']++;
            } else {
                $porCarga[$clave]['pendientes'][] = (string) $fila['codigo'];
            }
        }

        // Las que faltan por escanear primero.
        usort($porCarga, static fn (array $a, array $b) => [$a['escaneadas'] >= $a['total'], $a['tracking']] <=> [$b['escaneadas'] >= $b['total'], $b['tracking']]);

        return $porCarga;
    }

    public function ultimaConfirmadaConCarga(string $cargaId): ?SalidaOrden
    {
        $stmt = $this->connection->prepare(
            "SELECT so.id
             FROM salida_ordenes so
             JOIN salida_lineas sl ON sl.salida_orden_id = so.id
             JOIN lpns l ON l.id = sl.lpn_id
             WHERE l.carga_id = :carga_id AND so.estado = 'confirmada'
             ORDER BY so.confirmed_at DESC
             LIMIT 1"
        );
        $stmt->execute([':carga_id' => $cargaId]);
        $id = $stmt->fetchColumn();

        return $id !== false ? $this->findById((string) $id) : null;
    }

    public function search(array $filtros): array
    {
        $where = [];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[] = 's.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['clienteId'])) {
            // Tambien las salidas consolidadas que llevan cargas del cliente.
            $where[] = '(s.cliente_id = :cliente_id OR EXISTS (SELECT 1 FROM salida_lineas fl JOIN lpns fp ON fp.id = fl.lpn_id WHERE fl.salida_orden_id = s.id AND fp.cliente_id = :cliente_id_lpn))';
            $params[':cliente_id_lpn'] = $filtros['clienteId'];
            $params[':cliente_id'] = $filtros['clienteId'];
        }

        $sql = 'SELECT s.*, n.nombre AS naviera_nombre FROM salida_ordenes s LEFT JOIN navieras n ON n.id = s.naviera_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY s.secuencia DESC';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return array_map(
            fn (array $row) => $this->hydrate($row, $this->hydrarLineas((string) $row['id'])),
            $stmt->fetchAll(),
        );
    }

    /** @return array<string, ?string> */
    private function parametrosEmbarque(SalidaOrden $orden): array
    {
        $e = $orden->embarque();

        return [
            ':numero_contenedor' => $e->numeroContenedor,
            ':numero_sello' => $e->numeroSello,
            ':naviera_id' => $e->navieraId,
            ':nombre_barco' => $e->nombreBarco,
            ':fecha_cargue' => $e->fechaCargue?->format('Y-m-d'),
            ':fecha_salida_etd' => $e->fechaSalidaEtd?->format('Y-m-d'),
            ':destino_final' => $e->destinoFinal,
        ];
    }

    public function manifiesto(string $ordenId): array
    {
        // Una fila por carga: los bultos son los LPN de esa carga que van en la salida; kilos,
        // m3 y valor salen de las lineas de la carga, prorrateados si solo sale parte de sus bultos.
        // En las lineas el peso y el valor son totales de la linea y el cubicaje (p3) es por pieza.
        // Las cajas reempacadas se abren (genealogia) hasta sus bultos originales, que son los que tienen carga.
        $stmt = $this->connection->prepare(
            "WITH RECURSIVE contenido (lpn_id) AS (
                 SELECT sl.lpn_id FROM salida_lineas sl WHERE sl.salida_orden_id = :orden
                 UNION
                 SELECT g.lpn_origen_id FROM contenido x JOIN lpn_genealogia g ON g.lpn_destino_id = x.lpn_id
             )
             SELECT ca.tracking_numero, ca.proveedor, ca.fecha_ingreso, ca.aduanero_nombre, c.nombre AS cliente_nombre,
                    COUNT(*) AS bultos_salida,
                    (SELECT COUNT(*) FROM lpns l2 WHERE l2.carga_id = ca.id) AS bultos_carga,
                    GROUP_CONCAT(DISTINCT NULLIF(l.marca, '') ORDER BY l.marca SEPARATOR ', ') AS marcas,
                    t.kilos, t.valor, t.pies3, t.descripciones
             FROM contenido x
             JOIN lpns l ON l.id = x.lpn_id AND l.carga_id IS NOT NULL
             JOIN cargas ca ON ca.id = l.carga_id
             JOIN clientes c ON c.id = l.cliente_id
             LEFT JOIN (
                 SELECT carga_id, SUM(peso) AS kilos, SUM(valor) AS valor,
                        SUM(COALESCE(cubicaje_p3, ancho * alto * largo / 28316.846592) * cantidad) AS pies3,
                        GROUP_CONCAT(DISTINCT NULLIF(descripcion, '') ORDER BY orden SEPARATOR ', ') AS descripciones
                 FROM carga_lineas GROUP BY carga_id
             ) t ON t.carga_id = ca.id
             GROUP BY ca.id, ca.tracking_numero, ca.proveedor, ca.fecha_ingreso, ca.aduanero_nombre, c.nombre, t.kilos, t.valor, t.pies3, t.descripciones
             ORDER BY ca.proveedor, ca.tracking_numero"
        );
        $stmt->execute([':orden' => $ordenId]);

        return array_map(static function (array $r): array {
            $factor = (int) $r['bultos_carga'] > 0 ? (int) $r['bultos_salida'] / (int) $r['bultos_carga'] : 1.0;

            return [
                'cliente' => (string) $r['cliente_nombre'],
                'marca' => (string) ($r['marcas'] ?? ''),
                'compania' => $r['proveedor'] !== null ? (string) $r['proveedor'] : 'Reempaque',
                'tracking' => $r['tracking_numero'] !== null ? (string) $r['tracking_numero'] : null,
                'bultos' => (int) $r['bultos_salida'],
                'kilos' => round((float) $r['kilos'] * $factor, 2),
                'm3' => round((float) $r['pies3'] * 0.028316846592 * $factor, 3),
                'pies3' => round((float) $r['pies3'] * $factor, 2),
                'valor' => round((float) $r['valor'] * $factor, 2),
                'fechaIngreso' => $r['fecha_ingreso'] !== null ? (string) $r['fecha_ingreso'] : null,
                'aduanero' => (string) ($r['aduanero_nombre'] ?? ''),
                'descripcion' => (string) ($r['descripciones'] ?? ''),
            ];
        }, $stmt->fetchAll());
    }

    /** @return array<string, mixed>|null */
    private function findHeader(string $id): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT s.*, n.nombre AS naviera_nombre FROM salida_ordenes s
             LEFT JOIN navieras n ON n.id = s.naviera_id WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<int, SalidaLinea> */
    private function hydrarLineas(string $ordenId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT sl.lpn_id, sl.escaneado, sl.escaneado_at, l.codigo
             FROM salida_lineas sl
             INNER JOIN lpns l ON l.id = sl.lpn_id
             WHERE sl.salida_orden_id = :salida_orden_id'
        );
        $stmt->execute([':salida_orden_id' => $ordenId]);

        return array_map(
            fn (array $row) => new SalidaLinea(
                (string) $row['lpn_id'],
                (string) $row['codigo'],
                ((int) $row['escaneado']) === 1,
                $row['escaneado_at'] !== null ? new DateTimeImmutable((string) $row['escaneado_at']) : null,
            ),
            $stmt->fetchAll(),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, SalidaLinea> $lineas
     */
    private function hydrate(array $row, array $lineas): SalidaOrden
    {
        return new SalidaOrden(
            new Uuid((string) $row['id']),
            $row['cliente_id'] !== null ? (string) $row['cliente_id'] : null,
            $row['aduanero_identificador'] !== null ? (string) $row['aduanero_identificador'] : null,
            EstadoSalidaOrden::from((string) $row['estado']),
            (string) $row['creado_por_usuario_id'],
            new DateTimeImmutable((string) $row['created_at']),
            $row['confirmed_at'] !== null ? new DateTimeImmutable((string) $row['confirmed_at']) : null,
            $lineas,
            $row['codigo'] !== null ? (string) $row['codigo'] : null,
            $row['secuencia'] !== null ? (int) $row['secuencia'] : null,
            isset($row['do_anio']) ? (int) $row['do_anio'] : null,
            isset($row['do_numero']) ? (int) $row['do_numero'] : null,
            DatosEmbarque::desdeArreglo([
                'numeroContenedor' => $row['numero_contenedor'] ?? null,
                'numeroSello' => $row['numero_sello'] ?? null,
                'navieraId' => $row['naviera_id'] ?? null,
                'navieraNombre' => $row['naviera_nombre'] ?? null,
                'nombreBarco' => $row['nombre_barco'] ?? null,
                'fechaCargue' => $row['fecha_cargue'] ?? null,
                'fechaSalidaEtd' => $row['fecha_salida_etd'] ?? null,
                'destinoFinal' => $row['destino_final'] ?? null,
            ]),
        );
    }
}
