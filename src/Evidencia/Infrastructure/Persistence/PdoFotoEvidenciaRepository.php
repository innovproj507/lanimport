<?php

declare(strict_types=1);

namespace Courier\Evidencia\Infrastructure\Persistence;

use Courier\Evidencia\Domain\FotoEvidencia;
use Courier\Evidencia\Domain\FotoEvidenciaRepositoryInterface;
use Courier\Evidencia\Domain\TipoEvidencia;
use DateTimeImmutable;
use PDO;

final class PdoFotoEvidenciaRepository implements FotoEvidenciaRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(FotoEvidencia $foto): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO fotos_evidencia (id, tipo, carga_id, salida_orden_id, ruta_archivo, usuario_id, created_at)
             VALUES (:id, :tipo, :carga_id, :salida_orden_id, :ruta_archivo, :usuario_id, :created_at)'
        );

        $stmt->execute([
            ':id' => $foto->id(),
            ':tipo' => $foto->tipo()->value,
            ':carga_id' => $foto->cargaId(),
            ':salida_orden_id' => $foto->salidaOrdenId(),
            ':ruta_archivo' => $foto->rutaArchivo(),
            ':usuario_id' => $foto->usuarioId(),
            ':created_at' => $foto->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?FotoEvidencia
    {
        $stmt = $this->connection->prepare('SELECT * FROM fotos_evidencia WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return FotoEvidencia::reconstituir(
            (string) $row['id'],
            (string) $row['tipo'],
            $row['carga_id'] !== null ? (string) $row['carga_id'] : null,
            $row['salida_orden_id'] !== null ? (string) $row['salida_orden_id'] : null,
            (string) $row['ruta_archivo'],
            $row['usuario_id'] !== null ? (string) $row['usuario_id'] : null,
            new DateTimeImmutable((string) $row['created_at']),
        );
    }

    public function delete(string $id): void
    {
        $this->connection->prepare('DELETE FROM fotos_evidencia WHERE id = :id')->execute([':id' => $id]);
    }

    public function listar(TipoEvidencia $tipo, string $referenciaId, ?string $cargaId = null): array
    {
        $columna = $tipo === TipoEvidencia::Recepcion ? 'carga_id' : 'salida_orden_id';
        $parametros = [':tipo' => $tipo->value, ':referencia' => $referenciaId];
        $filtroCarga = '';

        if ($tipo === TipoEvidencia::Salida && $cargaId !== null) {
            $filtroCarga = ' AND f.carga_id = :carga';
            $parametros[':carga'] = $cargaId;
        }

        $stmt = $this->connection->prepare(
            "SELECT f.id, f.ruta_archivo, f.created_at, f.carga_id, u.nombre AS usuario
             FROM fotos_evidencia f
             LEFT JOIN usuarios u ON u.id = f.usuario_id
             WHERE f.tipo = :tipo AND f.{$columna} = :referencia{$filtroCarga}
             ORDER BY f.created_at DESC, f.id"
        );
        $stmt->execute($parametros);

        return array_map(self::fila(...), $stmt->fetchAll());
    }

    public function listarSalidasDeCarga(string $cargaId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT f.id, f.ruta_archivo, f.created_at, f.carga_id, f.salida_orden_id, u.nombre AS usuario,
                    s.do_numero, s.do_anio, s.codigo
             FROM fotos_evidencia f
             JOIN salida_ordenes s ON s.id = f.salida_orden_id
             LEFT JOIN usuarios u ON u.id = f.usuario_id
             WHERE f.tipo = 'salida' AND f.carga_id = :carga
             ORDER BY f.created_at DESC, f.id"
        );
        $stmt->execute([':carga' => $cargaId]);

        return array_map(static fn (array $row) => self::fila($row) + [
            'salidaOrdenId' => (string) $row['salida_orden_id'],
            'etiquetaDo' => $row['do_numero'] !== null ? "DO.{$row['do_numero']} / {$row['do_anio']}" : (string) $row['codigo'],
        ], $stmt->fetchAll());
    }

    public function contarSalidaPorCarga(string $salidaOrdenId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT carga_id, COUNT(*) FROM fotos_evidencia
             WHERE tipo = 'salida' AND salida_orden_id = :orden AND carga_id IS NOT NULL GROUP BY carga_id"
        );
        $stmt->execute([':orden' => $salidaOrdenId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: string, ruta: string, usuario: ?string, fecha: string, cargaId: ?string}
     */
    private static function fila(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'ruta' => (string) $row['ruta_archivo'],
            'usuario' => $row['usuario'] !== null ? (string) $row['usuario'] : null,
            'fecha' => (string) $row['created_at'],
            'cargaId' => $row['carga_id'] !== null ? (string) $row['carga_id'] : null,
        ];
    }

    public function contarRecepcionPorCargas(array $cargaIds): array
    {
        if ($cargaIds === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($cargaIds), '?'));
        $stmt = $this->connection->prepare(
            "SELECT carga_id, COUNT(*) FROM fotos_evidencia
             WHERE tipo = 'recepcion' AND carga_id IN ({$marcas}) GROUP BY carga_id"
        );
        $stmt->execute(array_values($cargaIds));

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }
}
