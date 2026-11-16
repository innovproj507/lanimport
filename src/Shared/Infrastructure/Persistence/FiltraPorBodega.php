<?php

declare(strict_types=1);

namespace Courier\Shared\Infrastructure\Persistence;

use Courier\Shared\Domain\BodegaActivaInterface;
use PDO;
use PDOStatement;

/**
 * Para los repositorios de lectura de indicadores (dashboard, reportes): con una bodega activa,
 * cada consulta cuenta solo lo de esa bodega. Las tablas cargas (bodega de ingreso) y lpns (donde
 * esta el bulto) se cambian por su parte de la bodega. salida_ordenes no: un DO puede juntar
 * bultos de dos bodegas y cada una cuenta los suyos por lpns. Sin bodega activa, todo igual.
 *
 * Requiere las propiedades $connection (PDO) y $bodegaActiva (?BodegaActivaInterface).
 */
trait FiltraPorBodega
{
    private function porBodega(string $sql): string
    {
        $bodega = $this->bodegaActiva instanceof BodegaActivaInterface ? $this->bodegaActiva->id() : null;

        if ($bodega === null) {
            return $sql;
        }

        $q = $this->connection->quote($bodega);

        return (string) preg_replace_callback(
            '/\b(FROM|JOIN)\s+(cargas|lpns)\b(\s+(?!WHERE\b|GROUP\b|ORDER\b|JOIN\b|LEFT\b|INNER\b|ON\b|LIMIT\b|USING\b)([a-z]\w*))?/i',
            static fn (array $m) => "{$m[1]} (SELECT * FROM {$m[2]} WHERE bodega_id = {$q}) " . (($m[4] ?? '') !== '' ? $m[4] : $m[2]),
            $sql,
        );
    }

    private function query(string $sql): PDOStatement
    {
        return $this->connection->query($this->porBodega($sql));
    }

    private function prepare(string $sql): PDOStatement
    {
        return $this->connection->prepare($this->porBodega($sql));
    }
}
