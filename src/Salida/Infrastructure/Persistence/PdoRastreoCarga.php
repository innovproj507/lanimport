<?php

declare(strict_types=1);

namespace Courier\Salida\Infrastructure\Persistence;

use Courier\Carga\Domain\CargaStatus;
use Courier\Salida\Domain\RastreoCargaInterface;
use PDO;
use PDOException;

final class PdoRastreoCarga implements RastreoCargaInterface
{
    /**
     * Bultos de la carga y el LPN con que viajan: el propio, o la caja de reempaque que lo contiene.
     */
    private const BULTOS_Y_CAJAS = "SELECT l.id AS bulto_id, l.id AS viaja_id FROM lpns l WHERE l.carga_id = :c1
        UNION ALL
        SELECT g.lpn_origen_id, g.lpn_destino_id FROM lpn_genealogia g JOIN lpns o ON o.id = g.lpn_origen_id WHERE o.carga_id = :c2";

    public function __construct(private readonly PDO $connection)
    {
    }

    public function caja(string $codigo): ?array
    {
        $codigo = strtoupper(str_replace(["'", '?', '_', ' '], ['-', '-', '-', ''], trim($codigo)));
        $stmt = $this->connection->prepare(
            "SELECT l.id, l.codigo, l.estado, l.marca, l.created_at, cl.codigo AS cliente_codigo,
                    TRIM(CONCAT(COALESCE(cl.nombre, ''), ' ', COALESCE(cl.apellido, ''))) AS cliente,
                    bo.nombre AS bodega, u.codigo AS ubicacion
             FROM lpns l
             LEFT JOIN clientes cl ON cl.id = l.cliente_id
             LEFT JOIN bodegas bo ON bo.id = l.bodega_id
             LEFT JOIN ubicaciones u ON u.id = l.ubicacion_id
             WHERE l.codigo = ? AND l.carga_id IS NULL LIMIT 1"
        );
        $stmt->execute([$codigo]);
        $caja = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($caja === false) {
            return null;
        }

        $id = (string) $caja['id'];

        $rep = $this->connection->prepare(
            "SELECT ro.id, ro.codigo, ro.estado FROM reempaque_lpns rl JOIN reempaque_ordenes ro ON ro.id = rl.reempaque_orden_id
             WHERE rl.lpn_id = ? AND rl.rol = 'destino' LIMIT 1"
        );
        $rep->execute([$id]);
        $reempaque = $rep->fetch(PDO::FETCH_ASSOC) ?: null;

        // Si a su vez se reempaco dentro de otra caja.
        $padre = $this->connection->prepare(
            'SELECT d.codigo FROM lpn_genealogia g JOIN lpns d ON d.id = g.lpn_destino_id WHERE g.lpn_origen_id = ? LIMIT 1'
        );
        $padre->execute([$id]);
        $dentroDe = $padre->fetchColumn();

        // Contenido: se abre la caja (a cualquier nivel) hasta los bultos originales, por carga.
        $cont = $this->connection->prepare(
            "WITH RECURSIVE contenido (lpn_id) AS (
                 SELECT g.lpn_origen_id FROM lpn_genealogia g WHERE g.lpn_destino_id = ?
                 UNION
                 SELECT g.lpn_origen_id FROM contenido x JOIN lpn_genealogia g ON g.lpn_destino_id = x.lpn_id
             )
             SELECT ca.id, ca.tracking_numero, COALESCE(ca.proveedor, '') AS proveedor,
                    TRIM(CONCAT(COALESCE(cl.nombre, ''), ' ', COALESCE(cl.apellido, ''))) AS cliente,
                    GROUP_CONCAT(DISTINCT NULLIF(l.marca, '') SEPARATOR ', ') AS marca,
                    COUNT(*) AS bultos, GROUP_CONCAT(l.codigo ORDER BY l.secuencia SEPARATOR ', ') AS etiquetas
             FROM contenido x
             JOIN lpns l ON l.id = x.lpn_id AND l.carga_id IS NOT NULL
             JOIN cargas ca ON ca.id = l.carga_id
             LEFT JOIN clientes cl ON cl.id = ca.cliente_id
             GROUP BY ca.id, ca.tracking_numero, ca.proveedor, cl.nombre, cl.apellido
             ORDER BY ca.tracking_numero"
        );
        $cont->execute([$id]);

        $ord = $this->connection->prepare(
            'SELECT so.id, so.codigo, so.do_numero, so.do_anio, so.estado, so.numero_contenedor, sl.escaneado
             FROM salida_lineas sl JOIN salida_ordenes so ON so.id = sl.salida_orden_id
             WHERE sl.lpn_id = ? ORDER BY so.created_at DESC'
        );
        $ord->execute([$id]);

        return [
            'id' => $id,
            'codigo' => (string) $caja['codigo'],
            'estado' => (string) $caja['estado'],
            'marca' => $caja['marca'] !== null ? (string) $caja['marca'] : null,
            'cliente' => (string) $caja['cliente'],
            'clienteCodigo' => $caja['cliente_codigo'] !== null ? (string) $caja['cliente_codigo'] : null,
            'bodega' => $caja['bodega'] !== null ? (string) $caja['bodega'] : null,
            'ubicacion' => $caja['ubicacion'] !== null ? (string) $caja['ubicacion'] : null,
            'creada' => (string) $caja['created_at'],
            'reempaque' => $reempaque !== null ? ['id' => (string) $reempaque['id'], 'codigo' => (string) $reempaque['codigo'], 'estado' => (string) $reempaque['estado']] : null,
            'dentroDe' => $dentroDe !== false ? (string) $dentroDe : null,
            'contenido' => array_map(static fn (array $r) => [
                'cargaId' => (string) $r['id'],
                'tracking' => (string) $r['tracking_numero'],
                'cliente' => (string) $r['cliente'],
                'proveedor' => (string) $r['proveedor'],
                'marca' => (string) ($r['marca'] ?? ''),
                'bultos' => (int) $r['bultos'],
                'etiquetas' => (string) $r['etiquetas'],
            ], $cont->fetchAll(PDO::FETCH_ASSOC)),
            'ordenes' => array_map(static fn (array $r) => [
                'id' => (string) $r['id'],
                'etiqueta' => self::etiqueta($r),
                'esOs' => $r['do_numero'] === null,
                'estado' => (string) $r['estado'],
                'contenedor' => $r['numero_contenedor'] !== null && $r['numero_contenedor'] !== '' ? (string) $r['numero_contenedor'] : null,
                'escaneado' => (bool) $r['escaneado'],
            ], $ord->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    public function detalle(string $cargaId): array
    {
        return [
            'carga' => $this->carga($cargaId),
            'ordenes' => $this->ordenes($cargaId),
            'bultos' => $this->bultos($cargaId),
        ];
    }

    /** @return array<string, mixed> */
    private function carga(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT TRIM(CONCAT(COALESCE(cl.nombre, ''), ' ', COALESCE(cl.apellido, ''))) AS cliente,
                    COALESCE(a.nombre, c.aduanero_nombre, c.aduanero_identificador, '') AS aduanero,
                    COALESCE(p.nombre, c.proveedor, '') AS proveedor,
                    u.nombre AS registrado_por, c.fecha_ingreso, c.numero_contenedor, c.notas, bo.nombre AS bodega
             FROM cargas c
             LEFT JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN aduaneros a ON a.identificador = c.aduanero_identificador
             LEFT JOIN proveedores p ON p.identificador = c.proveedor_identificador
             LEFT JOIN usuarios u ON u.id = c.registrado_por_usuario_id
             LEFT JOIN bodegas bo ON bo.id = c.bodega_id
             WHERE c.id = :id"
        );
        $stmt->execute([':id' => $cargaId]);
        $r = $stmt->fetch() ?: [];

        return [
            'cliente' => (string) ($r['cliente'] ?? ''),
            'aduanero' => (string) ($r['aduanero'] ?? ''),
            'proveedor' => (string) ($r['proveedor'] ?? ''),
            'registradoPor' => $r['registrado_por'] ?? null,
            'fechaIngreso' => $r['fecha_ingreso'] ?? null,
            'contenedor' => ($r['numero_contenedor'] ?? '') !== '' ? $r['numero_contenedor'] : null,
            'notas' => ($r['notas'] ?? '') !== '' ? $r['notas'] : null,
            'bodega' => $r['bodega'] ?? null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ordenes(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT so.id, so.codigo, so.do_numero, so.do_anio, so.estado, so.numero_contenedor, so.numero_sello,
                    n.nombre AS naviera, so.nombre_barco, so.fecha_cargue, so.fecha_salida_etd, so.destino_final, so.nota, so.comentario_cierre,
                    so.created_at, so.confirmed_at,
                    COUNT(*) AS bultos, SUM(sl.escaneado) AS escaneados
             FROM (" . self::BULTOS_Y_CAJAS . ") b
             JOIN salida_lineas sl ON sl.lpn_id = b.viaja_id
             JOIN salida_ordenes so ON so.id = sl.salida_orden_id
             LEFT JOIN navieras n ON n.id = so.naviera_id
             GROUP BY so.id
             ORDER BY so.created_at DESC"
        );
        $stmt->execute([':c1' => $cargaId, ':c2' => $cargaId]);

        return array_map(static fn (array $r) => [
            'id' => (string) $r['id'],
            'etiqueta' => self::etiqueta($r),
            'esOs' => $r['do_numero'] === null,
            'estado' => (string) $r['estado'],
            'contenedor' => $r['numero_contenedor'] ?: null,
            'sello' => $r['numero_sello'] ?: null,
            'naviera' => $r['naviera'] ?: null,
            'barco' => $r['nombre_barco'] ?: null,
            'fechaCargue' => $r['fecha_cargue'] ?: null,
            'etd' => $r['fecha_salida_etd'] ?: null,
            'destino' => $r['destino_final'] ?: null,
            // Cancelada: por que; confirmada: el comentario de quien cerro el DO.
            'nota' => ($r['nota'] ?: null) ?? (!empty($r['comentario_cierre']) ? 'Cierre: ' . $r['comentario_cierre'] : null),
            'creada' => (string) $r['created_at'],
            'confirmada' => $r['confirmed_at'] ?: null,
            'bultos' => (int) $r['bultos'],
            'escaneados' => (int) $r['escaneados'],
        ], $stmt->fetchAll());
    }

    /** @return array<int, array<string, mixed>> */
    private function bultos(string $cargaId): array
    {
        // Orden vigente (no cancelada) de cada bulto; si estuvo en varias, la mas reciente.
        $stmt = $this->connection->prepare(
            "SELECT b.bulto_id, b.viaja_id, so.id, so.codigo, so.do_numero, so.do_anio, so.estado, sl.escaneado
             FROM (" . self::BULTOS_Y_CAJAS . ") b
             JOIN salida_lineas sl ON sl.lpn_id = b.viaja_id
             JOIN salida_ordenes so ON so.id = sl.salida_orden_id
             WHERE so.estado <> 'cancelada'
             ORDER BY so.created_at"
        );
        $stmt->execute([':c1' => $cargaId, ':c2' => $cargaId]);
        $ordenPorBulto = [];

        foreach ($stmt->fetchAll() as $r) {
            $ordenPorBulto[$r['bulto_id']] = $r;
        }

        $stmt = $this->connection->prepare(
            "SELECT l.id, l.codigo, l.estado, u.codigo AS ubicacion, bl.nombre AS bodega,
                    (SELECT d.codigo FROM lpn_genealogia g JOIN lpns d ON d.id = g.lpn_destino_id
                     WHERE g.lpn_origen_id = l.id ORDER BY g.created_at DESC LIMIT 1) AS caja
             FROM lpns l
             LEFT JOIN ubicaciones u ON u.id = l.ubicacion_id
             LEFT JOIN bodegas bl ON bl.id = l.bodega_id
             WHERE l.carga_id = :c
             ORDER BY l.orden_en_carga, l.codigo"
        );
        $stmt->execute([':c' => $cargaId]);

        return array_map(static function (array $r) use ($ordenPorBulto) {
            $o = $ordenPorBulto[$r['id']] ?? null;

            return [
                'codigo' => (string) $r['codigo'],
                'estado' => (string) $r['estado'],
                'ubicacion' => $r['ubicacion'] ?: null,
                'bodega' => $r['bodega'] ?? null,
                'caja' => $r['caja'] ?: null,
                'ordenId' => $o !== null ? (string) $o['id'] : null,
                'orden' => $o !== null ? self::etiqueta($o) : null,
                'ordenEstado' => $o !== null ? (string) $o['estado'] : null,
                'escaneado' => $o !== null ? (bool) $o['escaneado'] : null,
            ];
        }, $stmt->fetchAll());
    }

    private const TITULOS = [
        'salida_agregada' => 'Orden de salida',
        'salida_ajuste' => 'Ajuste de bultos',
        'devuelta_bodega' => 'Devuelta a bodega',
        'devuelta_proveedor' => 'Devuelta al proveedor',
        'devolucion_orden' => 'Orden de devolucion',
        'orden_cancelada' => 'Orden cancelada',
        'salida_movida' => 'Movida a otra orden',
        'do_generado' => 'DO generado',
        'contenedor' => 'Contenedor asignado',
        'salida_confirmada' => 'Salida confirmada',
        'recepcion_escaneo' => 'Recepcion (escaneo)',
        'etiquetas_generadas' => 'Etiquetas generadas',
        'etiquetas_eliminadas' => 'Etiquetas eliminadas',
        'carga_editada' => 'Carga editada',
        'reempaque' => 'Reempaque',
        'carga_eliminada' => 'Carga eliminada',
        'traslado' => 'Traslado entre bodegas',
        'traslado_llegada' => 'Llegada de traslado',
    ];

    public function movimientos(string $cargaId): array
    {
        $eventos = [];

        // 1. Cambios de estado de la carga.
        $stmt = $this->connection->prepare(
            'SELECT h.estado, h.comentario, h.fecha, u.nombre AS usuario FROM tracking_historial h
             LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.carga_id = :c'
        );
        $stmt->execute([':c' => $cargaId]);
        foreach ($stmt->fetchAll() as $r) {
            $eventos[] = $this->evento((string) $r['fecha'], 'estado', 'Estado: ' . (CargaStatus::tryFrom((string) $r['estado'])?->label() ?? $r['estado']), $r['comentario'], $r['usuario']);
        }

        // 2. Bitacora (OS/DO, reempaque, etiquetas, ediciones...). Los escaneos de recepcion se agrupan por dia.
        $registrados = [];
        $recepcion = [];
        $escaneosSalida = [];
        $llegadas = [];
        try {
            $stmt = $this->connection->prepare(
                'SELECT m.tipo, m.detalle, m.salida_orden_id, m.created_at, u.nombre AS usuario FROM carga_movimientos m
                 LEFT JOIN usuarios u ON u.id = m.usuario_id WHERE m.carga_id = :c ORDER BY m.created_at'
            );
            $stmt->execute([':c' => $cargaId]);
            $filas = $stmt->fetchAll();
        } catch (PDOException) {
            $filas = []; // migracion 065 aun no aplicada
        }
        foreach ($filas as $r) {
            $registrados[$r['tipo'] . '|' . ($r['salida_orden_id'] ?? '')] = true;

            // Llegadas de un traslado: una por bulto en la bitacora, agrupadas por traslado y dia.
            if ($r['tipo'] === 'traslado_llegada') {
                $clave = $r['detalle'] . '|' . substr((string) $r['created_at'], 0, 10);
                $llegadas[$clave] ??= ['n' => 0, 'detalle' => (string) $r['detalle'], 'desde' => (string) $r['created_at'], 'hasta' => (string) $r['created_at'], 'usuarios' => []];
                $llegadas[$clave]['n']++;
                $llegadas[$clave]['hasta'] = (string) $r['created_at'];
                $llegadas[$clave]['usuarios'][(string) $r['usuario']] = true;
                continue;
            }

            if ($r['tipo'] === 'recepcion_escaneo') {
                $dia = substr((string) $r['created_at'], 0, 10);
                $recepcion[$dia] ??= ['n' => 0, 'desde' => (string) $r['created_at'], 'hasta' => (string) $r['created_at'], 'usuarios' => []];
                $recepcion[$dia]['n']++;
                $recepcion[$dia]['hasta'] = (string) $r['created_at'];
                $recepcion[$dia]['usuarios'][(string) $r['usuario']] = true;
                continue;
            }

            if ($r['tipo'] === 'salida_escaneo') {
                $clave = $r['salida_orden_id'] . '|' . substr((string) $r['created_at'], 0, 10);
                $escaneosSalida[$clave] ??= ['n' => 0, 'orden' => (string) $r['detalle'], 'desde' => (string) $r['created_at'], 'hasta' => (string) $r['created_at'], 'usuarios' => []];
                $escaneosSalida[$clave]['n']++;
                $escaneosSalida[$clave]['hasta'] = (string) $r['created_at'];
                $escaneosSalida[$clave]['usuarios'][(string) $r['usuario']] = true;
                continue;
            }

            $eventos[] = $this->evento((string) $r['created_at'], (string) $r['tipo'], self::TITULOS[$r['tipo']] ?? (string) $r['tipo'], $r['detalle'], $r['usuario']);
        }
        foreach ($recepcion as $g) {
            $eventos[] = $this->evento($g['desde'], 'recepcion_escaneo', self::TITULOS['recepcion_escaneo'],
                "{$g['n']} bulto(s) escaneados en recepcion" . self::rango($g['desde'], $g['hasta']),
                implode(', ', array_filter(array_keys($g['usuarios']))));
        }

        foreach ($llegadas as $g) {
            $eventos[] = $this->evento($g['desde'], 'traslado', self::TITULOS['traslado_llegada'],
                "{$g['n']} bulto(s): {$g['detalle']}" . self::rango($g['desde'], $g['hasta']),
                implode(', ', array_filter(array_keys($g['usuarios']))));
        }

        foreach ($escaneosSalida as $g) {
            $eventos[] = $this->evento($g['desde'], 'escaneo_salida', 'Escaneo para salida',
                "{$g['n']} bulto(s) escaneados para {$g['orden']}" . self::rango($g['desde'], $g['hasta']),
                implode(', ', array_filter(array_keys($g['usuarios']))));
        }

        // 3. Escaneos para salida anteriores a la bitacora (de las lineas de cada OS/DO), por orden y dia.
        $stmt = $this->connection->prepare(
            "SELECT so.id, so.codigo, so.do_numero, so.do_anio, COUNT(*) AS n, MIN(sl.escaneado_at) AS desde, MAX(sl.escaneado_at) AS hasta
             FROM (" . self::BULTOS_Y_CAJAS . ") b
             JOIN salida_lineas sl ON sl.lpn_id = b.viaja_id AND sl.escaneado_at IS NOT NULL
             JOIN salida_ordenes so ON so.id = sl.salida_orden_id
             GROUP BY so.id, so.codigo, so.do_numero, so.do_anio, DATE(sl.escaneado_at)"
        );
        $stmt->execute([':c1' => $cargaId, ':c2' => $cargaId]);
        foreach ($stmt->fetchAll() as $r) {
            if (isset($registrados['salida_escaneo|' . $r['id']])) {
                continue;
            }
            $eventos[] = $this->evento((string) $r['desde'], 'escaneo_salida', 'Escaneo para salida',
                "{$r['n']} bulto(s) escaneados para " . self::etiqueta($r) . self::rango((string) $r['desde'], (string) $r['hasta']), null);
        }

        // 4. Salidas confirmadas antes de que existiera la bitacora.
        $stmt = $this->connection->prepare(
            "SELECT so.id, so.codigo, so.do_numero, so.do_anio, so.numero_contenedor, so.confirmed_at, COUNT(*) AS n
             FROM (" . self::BULTOS_Y_CAJAS . ") b
             JOIN salida_lineas sl ON sl.lpn_id = b.viaja_id
             JOIN salida_ordenes so ON so.id = sl.salida_orden_id AND so.estado = 'confirmada'
             GROUP BY so.id, so.codigo, so.do_numero, so.do_anio, so.numero_contenedor, so.confirmed_at"
        );
        $stmt->execute([':c1' => $cargaId, ':c2' => $cargaId]);
        foreach ($stmt->fetchAll() as $r) {
            if (!isset($registrados['salida_confirmada|' . $r['id']]) && $r['confirmed_at'] !== null) {
                $eventos[] = $this->evento((string) $r['confirmed_at'], 'salida_confirmada', self::TITULOS['salida_confirmada'],
                    "{$r['n']} bulto(s) salieron en " . self::etiqueta($r) . ($r['numero_contenedor'] ? ", contenedor {$r['numero_contenedor']}" : ''), null);
            }
        }

        // 5. Etiquetas generadas antes de la bitacora.
        if (!isset($registrados['etiquetas_generadas|'])) {
            $stmt = $this->connection->prepare('SELECT MIN(created_at) AS f, COUNT(*) AS n FROM lpns WHERE carga_id = :c');
            $stmt->execute([':c' => $cargaId]);
            $r = $stmt->fetch();
            if ($r !== false && (int) $r['n'] > 0) {
                $eventos[] = $this->evento((string) $r['f'], 'etiquetas_generadas', self::TITULOS['etiquetas_generadas'], "{$r['n']} etiqueta(s) (LPN) generadas", null);
            }
        }

        // 6. Fotos de evidencia, por tipo, orden y dia.
        $stmt = $this->connection->prepare(
            "SELECT f.tipo, f.salida_orden_id, so.codigo, so.do_numero, so.do_anio, MIN(f.created_at) AS f, COUNT(*) AS n,
                    GROUP_CONCAT(DISTINCT u.nombre SEPARATOR ', ') AS usuarios
             FROM fotos_evidencia f
             LEFT JOIN salida_ordenes so ON so.id = f.salida_orden_id
             LEFT JOIN usuarios u ON u.id = f.usuario_id
             WHERE f.carga_id = :c
             GROUP BY f.tipo, f.salida_orden_id, so.codigo, so.do_numero, so.do_anio, DATE(f.created_at)"
        );
        $stmt->execute([':c' => $cargaId]);
        foreach ($stmt->fetchAll() as $r) {
            $donde = $r['tipo'] === 'recepcion' ? 'de recepcion' : 'de salida' . ($r['codigo'] !== null || $r['do_numero'] !== null ? ' en ' . self::etiqueta($r) : '');
            $eventos[] = $this->evento((string) $r['f'], 'fotos', 'Fotos', "{$r['n']} foto(s) {$donde}", $r['usuarios']);
        }

        // 7. Avisos al cliente (correo / WhatsApp).
        $stmt = $this->connection->prepare('SELECT canal, asunto, destinatario_email, estado_envio, created_at FROM notificaciones WHERE carga_id = :c');
        $stmt->execute([':c' => $cargaId]);
        foreach ($stmt->fetchAll() as $r) {
            $canal = $r['canal'] === 'whatsapp' ? 'WhatsApp' : 'Correo';
            $partes = array_filter([(string) ($r['asunto'] ?? ''), (string) ($r['destinatario_email'] ?? ''), (string) ($r['estado_envio'] ?? '')]);
            $eventos[] = $this->evento((string) $r['created_at'], 'aviso', "Aviso al cliente ({$canal})", implode(' · ', $partes), null);
        }

        usort($eventos, static fn (array $a, array $b) => strcmp($b['fecha'], $a['fecha']));

        return $eventos;
    }

    public function eliminada(string $trackingNumero): ?array
    {
        try {
            // Tracking completo, o solo sus 6 caracteres finales.
            $stmt = $this->connection->prepare('SELECT * FROM cargas_eliminadas WHERE tracking_numero = :t OR tracking_numero LIKE :p ORDER BY eliminada_at DESC LIMIT 1');
            $stmt->execute([':t' => $trackingNumero, ':p' => strlen($trackingNumero) === 6 ? '%-' . $trackingNumero : $trackingNumero]);
            $r = $stmt->fetch();
        } catch (PDOException) {
            return null; // migracion 065 aun no aplicada
        }

        if ($r === false) {
            return null;
        }

        $datos = json_decode((string) ($r['datos'] ?? ''), true) ?: [];
        // La carga ya no tiene tracking_historial: su historial viene de la copia archivada.
        $movimientos = [];
        foreach ($datos['historial'] ?? [] as $h) {
            $movimientos[] = $this->evento((string) $h['fecha'], 'estado', 'Estado: ' . (CargaStatus::tryFrom((string) $h['estado'])?->label() ?? $h['estado']), $h['comentario'] ?? null, $h['usuario'] ?? null);
        }
        $movimientos = array_merge($movimientos, $this->movimientos((string) $r['id']));
        usort($movimientos, static fn (array $a, array $b) => strcmp($b['fecha'], $a['fecha']));

        return [
            'cargaId' => (string) $r['id'],
            'tracking' => (string) $r['tracking_numero'],
            'cliente' => $r['cliente'],
            'aduanero' => $r['aduanero'],
            'proveedor' => $r['proveedor'],
            'fechaIngreso' => $r['fecha_ingreso'],
            'registradoPor' => $r['registrado_por'],
            'motivo' => $r['motivo'],
            'eliminadaPor' => $r['eliminada_por'],
            'eliminadaAt' => (string) $r['eliminada_at'],
            'lineas' => $datos['lineas'] ?? [],
            'notas' => $datos['notas'] ?? null,
            'movimientos' => $movimientos,
        ];
    }

    /** @return array{fecha: string, tipo: string, titulo: string, detalle: ?string, usuario: ?string} */
    private function evento(string $fecha, string $tipo, string $titulo, mixed $detalle, mixed $usuario): array
    {
        return [
            'fecha' => $fecha,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'detalle' => $detalle !== null && $detalle !== '' ? (string) $detalle : null,
            'usuario' => $usuario !== null && $usuario !== '' ? (string) $usuario : null,
        ];
    }

    private static function rango(string $desde, string $hasta): string
    {
        $a = substr($desde, 11, 5);
        $b = substr($hasta, 11, 5);

        return $a === $b ? '' : " ({$a} a {$b})";
    }

    /** @param array<string, mixed> $r */
    public function buscarClientesYAduaneros(string $texto): array
    {
        $like = '%' . trim($texto) . '%';

        $stmt = $this->connection->prepare(
            "SELECT cl.id, TRIM(CONCAT(COALESCE(cl.nombre, ''), ' ', COALESCE(cl.apellido, ''))) AS nombre, cl.codigo,
                    (SELECT COUNT(*) FROM cargas c WHERE c.cliente_id = cl.id) AS cargas
             FROM clientes cl
             WHERE CONCAT_WS(' ', cl.nombre, cl.apellido) LIKE :t1 OR cl.empresa LIKE :t2 OR cl.codigo LIKE :t3
             ORDER BY cargas DESC, nombre
             LIMIT 20"
        );
        $stmt->execute([':t1' => $like, ':t2' => $like, ':t3' => $like]);
        $clientes = array_map(static fn (array $r) => [
            'id' => (string) $r['id'],
            'nombre' => (string) $r['nombre'],
            'codigo' => $r['codigo'] ?: null,
            'cargas' => (int) $r['cargas'],
        ], $stmt->fetchAll());

        $stmt = $this->connection->prepare(
            "SELECT a.identificador, a.nombre,
                    (SELECT COUNT(*) FROM cargas c WHERE c.aduanero_identificador = a.identificador) AS cargas
             FROM aduaneros a
             WHERE a.nombre LIKE :t1 OR a.identificador LIKE :t2
             ORDER BY cargas DESC, a.nombre
             LIMIT 20"
        );
        $stmt->execute([':t1' => $like, ':t2' => $like]);
        $aduaneros = array_map(static fn (array $r) => [
            'id' => (string) $r['identificador'],
            'nombre' => (string) $r['nombre'],
            'cargas' => (int) $r['cargas'],
        ], $stmt->fetchAll());

        return ['clientes' => $clientes, 'aduaneros' => $aduaneros];
    }

    public function cargasDe(?string $clienteId, ?string $aduanero, bool $todas, int $pagina, int $porPagina): array
    {
        $where = $clienteId !== null ? 'c.cliente_id = :d' : 'c.aduanero_identificador = :d';
        $params = [':d' => $clienteId ?? (string) $aduanero];

        if (!$todas) {
            // Sigue en la operacion: algun bulto por recibir, en bodega, en reempaque/traslado/
            // devolucion pendiente, o en una OS/DO que aun no sale. Sin etiquetas: si no salio aun.
            $where .= " AND (EXISTS (SELECT 1 FROM lpns l WHERE l.carga_id = c.id
                        AND l.estado IN ('receiving', 'available', 'reserved', 'in_repack', 'in_transfer', 'in_return', 'quarantine'))
                    OR (c.estado IN ('ingresado', 'en_bodega') AND NOT EXISTS (SELECT 1 FROM lpns l2 WHERE l2.carga_id = c.id)))";
        }

        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM cargas c WHERE {$where}");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $stmt = $this->connection->prepare(
            "SELECT l.estado, COUNT(*) AS n FROM lpns l JOIN cargas c ON c.id = l.carga_id WHERE {$where} GROUP BY l.estado"
        );
        $stmt->execute($params);
        $bultos = array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));

        $stmt = $this->connection->prepare(
            "SELECT c.id, c.tracking_numero FROM cargas c WHERE {$where}
             ORDER BY c.fecha_ingreso DESC, c.created_at DESC LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', max(1, $porPagina), PDO::PARAM_INT);
        $stmt->bindValue(':off', (max(1, $pagina) - 1) * max(1, $porPagina), PDO::PARAM_INT);
        $stmt->execute();
        $filas = $stmt->fetchAll();

        $stmt = $this->connection->prepare($clienteId !== null
            ? "SELECT TRIM(CONCAT(COALESCE(nombre, ''), ' ', COALESCE(apellido, ''))) FROM clientes WHERE id = :d"
            : 'SELECT nombre FROM aduaneros WHERE identificador = :d');
        $stmt->execute($params);

        return [
            'nombre' => ($n = $stmt->fetchColumn()) !== false ? (string) $n : null,
            'total' => $total,
            'cargaIds' => array_map(static fn ($r) => (string) $r['id'], $filas),
            'trackings' => array_map(static fn ($r) => (string) $r['tracking_numero'], $filas),
            'bultos' => $bultos,
        ];
    }

    private static function etiqueta(array $r): string
    {
        return $r['do_numero'] !== null ? "DO.{$r['do_numero']} / {$r['do_anio']}" : (string) $r['codigo'];
    }
}
