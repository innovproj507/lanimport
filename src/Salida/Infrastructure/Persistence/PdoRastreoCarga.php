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
                    u.nombre AS registrado_por, c.fecha_ingreso, c.numero_contenedor, c.notas
             FROM cargas c
             LEFT JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN aduaneros a ON a.identificador = c.aduanero_identificador
             LEFT JOIN proveedores p ON p.identificador = c.proveedor_identificador
             LEFT JOIN usuarios u ON u.id = c.registrado_por_usuario_id
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
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ordenes(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT so.id, so.codigo, so.do_numero, so.do_anio, so.estado, so.numero_contenedor, so.numero_sello,
                    n.nombre AS naviera, so.nombre_barco, so.fecha_cargue, so.fecha_salida_etd, so.destino_final, so.nota,
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
            'nota' => $r['nota'] ?: null,
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
            "SELECT l.id, l.codigo, l.estado, u.codigo AS ubicacion,
                    (SELECT d.codigo FROM lpn_genealogia g JOIN lpns d ON d.id = g.lpn_destino_id
                     WHERE g.lpn_origen_id = l.id ORDER BY g.created_at DESC LIMIT 1) AS caja
             FROM lpns l
             LEFT JOIN ubicaciones u ON u.id = l.ubicacion_id
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
    private static function etiqueta(array $r): string
    {
        return $r['do_numero'] !== null ? "DO.{$r['do_numero']} / {$r['do_anio']}" : (string) $r['codigo'];
    }
}
