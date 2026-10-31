<?php

declare(strict_types=1);

/**
 * Limpieza diaria de archivos (cron de Plesk, como el usuario del sitio):
 *   php bin/mantenimiento.php            aplica
 *   php bin/mantenimiento.php --simular  solo cuenta lo que haria
 *
 * 1. Borra las imagenes de etiquetas LPN de cargas ya despachadas (se regeneran si se piden).
 * 2. Pasa a blanco y negro (paleta de 2 colores, ~6 KB) las etiquetas que quedaron en color.
 * 3. Borra imagenes de etiquetas y fotos de evidencia que ya no tienen registro en la base.
 * 4. Borra sesiones de mas de 7 dias y logs de mas de 30 dias en writable/.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Courier\Lpn\Infrastructure\Barcode\LpnBarcodeGeneratorService;
use Courier\Shared\Infrastructure\Config;
use Courier\Shared\Infrastructure\Persistence\ConnectionFactory;

$basePath = dirname(__DIR__);
Config::load($basePath);

$simular = in_array('--simular', $argv, true);
$pdo = ConnectionFactory::getInstance();
$public = $basePath . '/public';
$dirLpns = $public . '/uploads/lpns';
$resumen = [];

$borrar = static function (string $archivo, string $tipo) use ($simular, &$resumen): void {
    $resumen[$tipo]['archivos'] = ($resumen[$tipo]['archivos'] ?? 0) + 1;
    $resumen[$tipo]['bytes'] = ($resumen[$tipo]['bytes'] ?? 0) + (int) @filesize($archivo);

    if (!$simular) {
        @unlink($archivo);
    }
};

// Estados de LPN por codigo, y si su carga ya salio despachada.
$despachadas = "'en_transito','en_aduana','liberado','listo_entrega','entregado'";
$lpns = [];
foreach ($pdo->query(
    "SELECT l.codigo, (c.estado IN ({$despachadas})) AS despachada
     FROM lpns l LEFT JOIN cargas c ON c.id = l.carga_id
     WHERE l.codigo IS NOT NULL"
) as $fila) {
    $lpns[(string) $fila['codigo']] = (bool) $fila['despachada'];
}

foreach (glob($dirLpns . '/*.png') ?: [] as $archivo) {
    $codigo = basename($archivo, '.png');

    if (!array_key_exists($codigo, $lpns)) {
        $borrar($archivo, 'etiquetas sin LPN');
        continue;
    }

    if ($lpns[$codigo]) {
        $borrar($archivo, 'etiquetas de cargas despachadas');
        continue;
    }

    // Byte 25 del PNG = tipo de color: 3 es paleta (ya liviana); 2 y 6 son color verdadero.
    $cabecera = (string) @file_get_contents($archivo, false, null, 0, 26);

    if (strlen($cabecera) === 26 && ord($cabecera[25]) !== 3) {
        $antes = (int) filesize($archivo);
        $resumen['etiquetas recomprimidas']['archivos'] = ($resumen['etiquetas recomprimidas']['archivos'] ?? 0) + 1;

        if (!$simular && ($imagen = @imagecreatefrompng($archivo)) !== false) {
            LpnBarcodeGeneratorService::aBlancoYNegro($imagen);
            imagepng($imagen, $archivo, 6);
            imagedestroy($imagen);
            clearstatcache(true, $archivo);
            $resumen['etiquetas recomprimidas']['bytes'] = ($resumen['etiquetas recomprimidas']['bytes'] ?? 0) + $antes - (int) filesize($archivo);
        }
    }
}

// Fotos de evidencia sin fila (p.ej. de un DO eliminado: la fila se borra en cascada, el archivo no).
$fotos = array_flip($pdo->query('SELECT ruta_archivo FROM fotos_evidencia')->fetchAll(PDO::FETCH_COLUMN));
$dirFotos = $public . '/uploads/evidencias';

if (is_dir($dirFotos)) {
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirFotos, FilesystemIterator::SKIP_DOTS));

    foreach ($iterador as $archivo) {
        $relativo = 'uploads/evidencias/' . str_replace('\\', '/', substr($archivo->getPathname(), strlen($dirFotos) + 1));

        // Margen de 1 hora: una foto se guarda en disco un instante antes que su fila.
        if (!isset($fotos[$relativo]) && $archivo->getMTime() < time() - 3600) {
            $borrar($archivo->getPathname(), 'fotos sin registro');
        }
    }
}

$viejos = static function (string $patron, int $dias, string $tipo) use ($borrar): void {
    foreach (glob($patron) ?: [] as $archivo) {
        if (is_file($archivo) && filemtime($archivo) < time() - $dias * 86400 && basename($archivo) !== 'index.html') {
            $borrar($archivo, $tipo);
        }
    }
};
$viejos($basePath . '/writable/session/*', 7, 'sesiones viejas');
$viejos($basePath . '/writable/logs/*.log', 30, 'logs viejos');

echo ($simular ? '[SIMULACION] ' : '') . date('Y-m-d H:i:s') . " mantenimiento\n";
foreach ($resumen as $tipo => $r) {
    printf("  %-34s %6d archivos  %8.1f MB\n", $tipo, $r['archivos'], ($r['bytes'] ?? 0) / 1048576);
}
if ($resumen === []) {
    echo "  nada que limpiar\n";
}
