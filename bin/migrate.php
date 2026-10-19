<?php

declare(strict_types=1);

/**
 * Migraciones de la base de datos (database/migrations, .sql o .php).
 *
 * Lleva registro en la tabla schema_migraciones: cada archivo se aplica una sola vez,
 * en orden de nombre (NNN_descripcion). Usa la conexion DB_* del .env (ConnectionFactory).
 *
 *   php bin/migrate.php              aplica las pendientes
 *   php bin/migrate.php --estado     lista aplicadas y pendientes, sin cambiar nada
 *   php bin/migrate.php --baseline   marca todas como aplicadas sin ejecutarlas
 *                                    (una sola vez, en una base que ya tiene el esquema al dia)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Courier\Shared\Infrastructure\Config;
use Courier\Shared\Infrastructure\Persistence\ConnectionFactory;

$basePath = dirname(__DIR__);
Config::load($basePath);

$connection = ConnectionFactory::getInstance();
$connection->exec(
    'CREATE TABLE IF NOT EXISTS schema_migraciones (
        archivo VARCHAR(190) NOT NULL PRIMARY KEY,
        aplicada_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$archivos = array_merge(
    glob($basePath . '/database/migrations/*.sql') ?: [],
    glob($basePath . '/database/migrations/*.php') ?: [],
);
usort($archivos, static fn (string $a, string $b): int => strcmp(basename($a), basename($b)));

$aplicadas = array_flip($connection->query('SELECT archivo FROM schema_migraciones')->fetchAll(PDO::FETCH_COLUMN));
$pendientes = array_values(array_filter($archivos, static fn (string $f): bool => !isset($aplicadas[basename($f)])));
$registrar = $connection->prepare('INSERT INTO schema_migraciones (archivo, aplicada_at) VALUES (:archivo, NOW())');

$opcion = $argv[1] ?? '';

if ($opcion === '--estado') {
    echo count($aplicadas) . " aplicadas, " . count($pendientes) . " pendientes.\n";
    foreach ($pendientes as $archivo) {
        echo '  pendiente: ' . basename($archivo) . "\n";
    }
    exit(0);
}

if ($opcion === '--baseline') {
    foreach ($pendientes as $archivo) {
        $registrar->execute([':archivo' => basename($archivo)]);
    }
    echo count($pendientes) . " migraciones marcadas como aplicadas (sin ejecutarlas).\n";
    exit(0);
}

if ($opcion !== '') {
    fwrite(STDERR, "Opcion desconocida: {$opcion}. Use --estado o --baseline.\n");
    exit(1);
}

if ($pendientes === []) {
    echo "No hay migraciones pendientes.\n";
    exit(0);
}

foreach ($pendientes as $archivo) {
    $nombre = basename($archivo);
    echo "Aplicando {$nombre}...\n";

    try {
        if (str_ends_with($archivo, '.php')) {
            (require $archivo)($connection);
        } else {
            $connection->exec((string) file_get_contents($archivo));
        }
    } catch (Throwable $e) {
        // Los ALTER/CREATE de MySQL no son transaccionales: se detiene para revisar a mano.
        fwrite(STDERR, "ERROR en {$nombre}: {$e->getMessage()}\nSe detuvo; las anteriores quedaron aplicadas.\n");
        exit(1);
    }

    $registrar->execute([':archivo' => $nombre]);
}

echo count($pendientes) . " migraciones aplicadas.\n";
