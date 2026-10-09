<?php

declare(strict_types=1);

namespace Courier\Carga\Infrastructure\Persistence;

use PDO;
use Ramsey\Uuid\Uuid;

final class PdoEtiquetaRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(string $cargaId, string $rutaArchivo): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO etiquetas (id, carga_id, ruta_archivo, formato, created_at)
             VALUES (:id, :carga_id, :ruta, :formato, NOW())'
        );

        $stmt->execute([
            ':id' => Uuid::uuid4()->toString(),
            ':carga_id' => $cargaId,
            ':ruta' => $rutaArchivo,
            ':formato' => 'code128_png',
        ]);
    }

    public function saveParaLpn(string $lpnId, string $rutaArchivo): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO etiquetas (id, lpn_id, ruta_archivo, formato, created_at)
             VALUES (:id, :lpn_id, :ruta, :formato, NOW())'
        );

        $stmt->execute([
            ':id' => Uuid::uuid4()->toString(),
            ':lpn_id' => $lpnId,
            ':ruta' => $rutaArchivo,
            ':formato' => 'code128_png',
        ]);
    }
}
