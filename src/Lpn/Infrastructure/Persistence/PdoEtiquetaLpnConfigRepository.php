<?php

declare(strict_types=1);

namespace Courier\Lpn\Infrastructure\Persistence;

use Courier\Lpn\Domain\EtiquetaLpnConfig;
use Courier\Lpn\Domain\EtiquetaLpnConfigRepositoryInterface;
use PDO;

final class PdoEtiquetaLpnConfigRepository implements EtiquetaLpnConfigRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function obtener(): EtiquetaLpnConfig
    {
        $row = $this->connection->query('SELECT * FROM lpn_etiqueta_config WHERE id = 1')->fetch();

        if ($row === false) {
            return EtiquetaLpnConfig::porDefecto();
        }

        return new EtiquetaLpnConfig(
            (bool) $row['mostrar_marca'],
            (bool) $row['mostrar_proveedor'],
            (bool) $row['mostrar_serie'],
            (bool) $row['mostrar_descripcion'],
        );
    }

    public function guardar(EtiquetaLpnConfig $config): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO lpn_etiqueta_config (id, mostrar_marca, mostrar_proveedor, mostrar_serie, mostrar_descripcion, updated_at)
             VALUES (1, :mostrar_marca, :mostrar_proveedor, :mostrar_serie, :mostrar_descripcion, NOW())
             ON DUPLICATE KEY UPDATE
                mostrar_marca = VALUES(mostrar_marca), mostrar_proveedor = VALUES(mostrar_proveedor),
                mostrar_serie = VALUES(mostrar_serie), mostrar_descripcion = VALUES(mostrar_descripcion),
                updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            ':mostrar_marca' => $config->mostrarMarca() ? 1 : 0,
            ':mostrar_proveedor' => $config->mostrarProveedor() ? 1 : 0,
            ':mostrar_serie' => $config->mostrarSerie() ? 1 : 0,
            ':mostrar_descripcion' => $config->mostrarDescripcion() ? 1 : 0,
        ]);
    }
}
