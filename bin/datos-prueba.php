<?php

declare(strict_types=1);

/**
 * Datos ficticios para probar en local (solo desarrollo, nunca en produccion):
 *   php bin/datos-prueba.php
 *
 * Crea, con los mismos casos de uso del sistema (asi todo queda consistente):
 *  - usuarios prueba.<rol>@lan.local (clave Prueba.2026!) para cada rol;
 *  - 6 clientes "PRUEBA ..." con correo @ejemplo.test (no se envia nada real) y 8 ubicaciones;
 *  - 16 cargas en distintos momentos: sin etiquetas, por escanear, parciales, en bodega,
 *    una grande (180 bultos), una dividida entre dos contenedores y una ya despachada;
 *  - un DO pendiente a medio escanear, un DO confirmado (cargas en transito) y un reempaque.
 * Se puede correr varias veces: cada corrida agrega otro juego de cargas.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Courier\Auth\Domain\Rol;
use Courier\Auth\Domain\Usuario;
use Courier\Auth\Infrastructure\Persistence\PdoUsuarioRepository;
use Courier\Auth\Infrastructure\Security\PasswordHasher;
use Courier\Carga\Application\RegistrarCarga\RegistrarCargaCommand;
use Courier\Carga\Application\RegistrarCarga\RegistrarCargaHandler;
use Courier\Cliente\Application\RegistrarCliente\RegistrarClienteCommand;
use Courier\Cliente\Application\RegistrarCliente\RegistrarClienteHandler;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnCommand;
use Courier\Lpn\Application\ConfirmarRecepcionLpn\ConfirmarRecepcionLpnHandler;
use Courier\Lpn\Application\GenerarImagenesEtiquetasLpn\GenerarImagenesEtiquetasLpnCommand;
use Courier\Lpn\Application\GenerarImagenesEtiquetasLpn\GenerarImagenesEtiquetasLpnHandler;
use Courier\Lpn\Application\GenerarLpnsParaCarga\GenerarLpnsParaCargaCommand;
use Courier\Lpn\Application\GenerarLpnsParaCarga\GenerarLpnsParaCargaHandler;
use Courier\Lpn\Application\RegistrarUbicacion\RegistrarUbicacionCommand;
use Courier\Lpn\Application\RegistrarUbicacion\RegistrarUbicacionHandler;
use Courier\Lpn\Domain\LpnRepositoryInterface;
use Courier\Reempaque\Application\IniciarReempaque\IniciarReempaqueCommand;
use Courier\Reempaque\Application\IniciarReempaque\IniciarReempaqueHandler;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaCommand;
use Courier\Salida\Application\ConfirmarSalida\ConfirmarSalidaHandler;
use Courier\Salida\Application\CrearOrdenSalida\CrearOrdenSalidaCommand;
use Courier\Salida\Application\CrearOrdenSalida\CrearOrdenSalidaHandler;
use Courier\Salida\Application\RegistrarEscaneoSalida\RegistrarEscaneoSalidaCommand;
use Courier\Salida\Application\RegistrarEscaneoSalida\RegistrarEscaneoSalidaHandler;
use Courier\Shared\Domain\ValueObject\EmailAddress;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Config;

$basePath = dirname(__DIR__);
Config::load($basePath);

// Proteccion: solo una base local de desarrollo.
$host = (string) Config::get('DB_HOST', '');
if (!in_array($host, ['127.0.0.1', 'localhost'], true) || !preg_match('/CI_ENVIRONMENT\s*=\s*development/', (string) @file_get_contents($basePath . '/.env'))) {
    fwrite(STDERR, "Solo para la base local de desarrollo (DB_HOST={$host}). No se hizo nada.\n");
    exit(1);
}

/** @var \Courier\Shared\Infrastructure\Container $c */
$c = require $basePath . '/bootstrap/courier.php';
$pdo = $c->get(PDO::class);
mt_srand(2026);
$corrida = date('His');

$paso = static function (string $texto): void {
    echo '· ' . $texto . "\n";
};

// --- Usuarios por rol --------------------------------------------------------
$usuarios = new PdoUsuarioRepository($pdo);
$hasher = new PasswordHasher();
$idsUsuario = [];
foreach (['admin' => 'Owner', 'gerente' => 'Gerencia', 'operaciones' => 'Trafico', 'bodega' => 'Bodega'] as $rol => $nombre) {
    $email = "prueba.{$rol}@lan.local";
    $existente = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $existente->execute([$email]);
    $id = $existente->fetchColumn();

    if ($id === false) {
        $usuario = Usuario::registrar(Uuid::generate(), "Prueba {$nombre}", new EmailAddress($email), $hasher->hash('Prueba.2026!'), Rol::from($rol));
        $usuarios->save($usuario);
        $id = $usuario->id();
    }
    $idsUsuario[$rol] = (string) $id;
}
$trafico = $idsUsuario['operaciones'];
$bodega = $idsUsuario['bodega'];
$paso('Usuarios prueba.{admin,gerente,operaciones,bodega}@lan.local / Prueba.2026!');

// --- Ubicaciones y catalogos ---------------------------------------------------
$ubicaciones = [];
foreach (['A-01', 'A-02', 'A-03', 'B-01', 'B-02', 'B-03', 'C-01', 'PISO'] as $codigo) {
    $buscar = $pdo->prepare('SELECT id FROM ubicaciones WHERE codigo = ?');
    $buscar->execute([$codigo]);
    if ($buscar->fetchColumn() === false) {
        $c->get(RegistrarUbicacionHandler::class)->handle(new RegistrarUbicacionCommand($codigo, 'Bodega ' . $codigo[0], $codigo[0], substr($codigo, 2)));
    }
    $buscar->execute([$codigo]);
    $ubicaciones[] = (string) $buscar->fetchColumn();
}

$aduaneros = $pdo->query('SELECT identificador, nombre FROM aduaneros WHERE activo = 1 ORDER BY secuencia LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);
$proveedores = $pdo->query('SELECT identificador, nombre FROM proveedores ORDER BY nombre LIMIT 12')->fetchAll(PDO::FETCH_ASSOC);
$navieraId = $pdo->query('SELECT id FROM navieras WHERE activo = 1 LIMIT 1')->fetchColumn() ?: null;

if ($aduaneros === [] || $proveedores === []) {
    fwrite(STDERR, "Faltan aduaneros o proveedores en la base local.\n");
    exit(1);
}

// --- Clientes ficticios ------------------------------------------------------
$nombresClientes = [
    ['PRUEBA ALMACEN', 'LA ESTRELLA', 'Almacen La Estrella S.A.', 'Panama'],
    ['PRUEBA DISTRIBUIDORA', 'DEL NORTE', 'Distribuidora del Norte', 'Republica Dominicana'],
    ['PRUEBA MODAS', 'CARIBE', 'Modas Caribe', 'Panama'],
    ['PRUEBA FERRETERIA', 'CENTRAL', 'Ferreteria Central', 'Colombia'],
    ['PRUEBA IMPORTADORA', 'SOL', 'Importadora Sol', 'Republica Dominicana'],
    ['PRUEBA CALZADO', 'URBANO', 'Calzado Urbano', 'Panama'],
];
$clientes = [];
foreach ($nombresClientes as $i => [$nombre, $apellido, $empresa, $pais]) {
    $email = 'cliente' . ($i + 1) . '@ejemplo.test';
    $existente = $pdo->prepare('SELECT id FROM clientes WHERE email = ?');
    $existente->execute([$email]);
    $id = $existente->fetchColumn();

    if ($id === false) {
        $id = $c->get(RegistrarClienteHandler::class)->handle(new RegistrarClienteCommand(
            $nombre, $email, '6' . str_pad((string) (1000000 + $i * 1111), 7, '0'), 'Zona Libre, Galera ' . ($i + 1),
            $apellido, $empresa, $pais, $aduaneros[$i % count($aduaneros)]['identificador'],
        ))->clienteId;
    }
    $clientes[] = (string) $id;
}
$paso(count($clientes) . ' clientes PRUEBA (correo @ejemplo.test) y ' . count($ubicaciones) . ' ubicaciones');

// --- Cargas ------------------------------------------------------------------
$mercancia = [
    ['ROPA DAMA', 'CAJAS'], ['ROPA CABALLERO', 'CAJAS'], ['CALZADO DEPORTIVO', 'CAJAS'], ['HERRAMIENTAS', 'BULTOS'],
    ['ELECTRODOMESTICOS', 'CAJAS'], ['JUGUETES', 'CAJAS'], ['TELAS', 'ROLLOS'], ['ACCESORIOS', 'SACOS'],
];
$marcas = ['ESTRELLA', 'NORTE', 'CARIBE', 'CENTRAL', 'SOL', 'URBANO', 'RJ', 'ALEN'];

$registrar = static function (int $n, int $bultos, int $diasAtras) use ($c, $clientes, $aduaneros, $proveedores, $mercancia, $marcas, $trafico, $corrida): string {
    $clienteIdx = $n % count($clientes);
    $aduanero = $aduaneros[$clienteIdx % count($aduaneros)];
    $proveedor = $proveedores[$n % count($proveedores)];
    $lineas = [];
    $restantes = $bultos;
    $nLineas = $bultos >= 6 ? mt_rand(1, 3) : 1;

    for ($l = 0; $l < $nLineas; $l++) {
        $cantidad = $l === $nLineas - 1 ? $restantes : max(1, intdiv($restantes, $nLineas - $l));
        $restantes -= $cantidad;
        [$descripcion, $unidad] = $mercancia[($n + $l) % count($mercancia)];
        $lineas[] = [
            'descripcion' => $descripcion, 'unidadMedida' => $unidad, 'cantidad' => $cantidad,
            'ancho' => 50, 'alto' => 40, 'largo' => 60,
            'peso' => round($cantidad * mt_rand(80, 250) / 10, 2),
            'valor' => round($cantidad * mt_rand(300, 1500) / 10, 2),
            'marca' => $marcas[$clienteIdx % count($marcas)],
        ];
    }

    return $c->get(RegistrarCargaHandler::class)->handle(new RegistrarCargaCommand(
        $clientes[$clienteIdx], $proveedor['nombre'], $proveedor['identificador'], $aduanero['identificador'], $aduanero['nombre'],
        null, null, [], $trafico, date('Y-m-d', strtotime("-{$diasAtras} days")), $lineas,
        "[DATOS DE PRUEBA {$corrida}] carga #{$n}", null, null, false,
        ['empresaTransporte' => 'Transportes Prueba', 'chofer' => 'Juan Perez', 'placa' => 'AB' . (1000 + $n), 'entregadoPor' => 'Proveedor', 'cedula' => '8-123-' . (400 + $n), 'tiposMercancia' => ['cajas']],
    ))->cargaId;
};

$lpnsDe = static function (string $cargaId) use ($c): array {
    $lpns = $c->get(LpnRepositoryInterface::class)->findByCargaId($cargaId);
    usort($lpns, static fn ($a, $b) => $a->ordenEnCarga() <=> $b->ordenEnCarga());

    return $lpns;
};

$etiquetar = static function (string $cargaId) use ($c, $trafico, $lpnsDe): array {
    $c->get(GenerarLpnsParaCargaHandler::class)->handle(new GenerarLpnsParaCargaCommand($cargaId, $trafico));
    $c->get(GenerarImagenesEtiquetasLpnHandler::class)->handle(new GenerarImagenesEtiquetasLpnCommand($cargaId, 120));

    return $lpnsDe($cargaId);
};

$escanear = static function (array $lpns, string $cargaId) use ($c, $bodega, $ubicaciones): void {
    foreach ($lpns as $i => $lpn) {
        $c->get(ConfirmarRecepcionLpnHandler::class)->handle(new ConfirmarRecepcionLpnCommand(
            (string) $lpn->codigo(), $ubicaciones[$i % count($ubicaciones)], $bodega, $cargaId,
        ));
    }
};

// [bultos, dias de ingreso, que hacer]
$plan = [
    [4, 0, 'sin etiquetas'], [7, 1, 'sin etiquetas'],
    [12, 1, 'por escanear'], [9, 2, 'por escanear'],
    [15, 3, 'parcial'], [20, 4, 'parcial'],
    [6, 5, 'en bodega'], [10, 6, 'en bodega'], [3, 7, 'en bodega'], [25, 8, 'en bodega'],
    [180, 2, 'grande en bodega'],
    [40, 9, 'para dividir'],
    [8, 10, 'reempaque'], [5, 10, 'reempaque'],
    [14, 12, 'despachar'], [11, 13, 'despachar'],
];

$listas = [];   // cargaId => LPNs escaneados y libres (para salidas)
$porCarga = [];
foreach ($plan as $n => [$bultos, $dias, $que]) {
    $cargaId = $registrar($n, $bultos, $dias);
    $porCarga[$que][] = $cargaId;

    if ($que === 'sin etiquetas') {
        continue;
    }

    $lpns = $etiquetar($cargaId);

    if ($que === 'por escanear') {
        continue;
    }

    $escanear($que === 'parcial' ? array_slice($lpns, 0, intdiv(count($lpns), 2)) : $lpns, $cargaId);
    $listas[$cargaId] = $que === 'parcial' ? array_slice($lpns, 0, intdiv(count($lpns), 2)) : $lpns;
}
$paso(count($plan) . ' cargas: 2 sin etiquetas, 2 por escanear, 2 parciales, 4 en bodega, 1 de 180 bultos, 1 para dividir, 2 para reempaque, 2 para despachar');

$ids = static fn (array $lpns): array => array_map(static fn ($l) => $l->id(), $lpns);
$contenedor = static fn (string $prefijo): string => $prefijo . str_pad((string) mt_rand(1000000, 9999999), 7, '0');

// --- DO confirmado: dos cargas completas, escaneadas y finalizadas ------------
$lpnsDespacho = array_merge(...array_map(static fn ($id) => $listas[$id], $porCarga['despachar']));
$doConfirmado = $c->get(CrearOrdenSalidaHandler::class)->handle(new CrearOrdenSalidaCommand(
    null, $ids($lpnsDespacho), null, $trafico,
    ['numeroContenedor' => $contenedor('MSCU'), 'numeroSello' => 'S' . mt_rand(100000, 999999), 'navieraId' => $navieraId,
     'nombreBarco' => 'MSC PRUEBA', 'fechaCargue' => date('Y-m-d', strtotime('-1 day')), 'fechaSalidaEtd' => date('Y-m-d', strtotime('+2 days')), 'destinoFinal' => 'Santo Domingo'],
));
foreach ($lpnsDespacho as $lpn) {
    $c->get(RegistrarEscaneoSalidaHandler::class)->handle(new RegistrarEscaneoSalidaCommand($doConfirmado, (string) $lpn->codigo()));
}
$c->get(ConfirmarSalidaHandler::class)->handle(new ConfirmarSalidaCommand($doConfirmado, $trafico));
$paso('DO confirmado con ' . count($lpnsDespacho) . ' bultos (sus cargas pasan a En transito)');

// --- DO pendiente: 3 cargas en bodega + 25 de los 40 de la carga dividida, a medio escanear ----
$enBodega = array_slice($porCarga['en bodega'], 0, 3);
$lpnsPendiente = array_merge(...array_map(static fn ($id) => $listas[$id], $enBodega));
$lpnsPendiente = array_merge($lpnsPendiente, array_slice($listas[$porCarga['para dividir'][0]], 0, 25));
$doPendiente = $c->get(CrearOrdenSalidaHandler::class)->handle(new CrearOrdenSalidaCommand(
    null, $ids($lpnsPendiente), null, $trafico,
    ['numeroContenedor' => $contenedor('CSNU'), 'navieraId' => $navieraId, 'nombreBarco' => 'COSCO PRUEBA', 'destinoFinal' => 'Colon'],
));
foreach (array_slice($lpnsPendiente, 0, intdiv(count($lpnsPendiente), 2)) as $lpn) {
    $c->get(RegistrarEscaneoSalidaHandler::class)->handle(new RegistrarEscaneoSalidaCommand($doPendiente, (string) $lpn->codigo()));
}
$paso('DO pendiente con ' . count($lpnsPendiente) . ' bultos (mitad escaneados; la carga de 40 va con 25 y quedan 15 en bodega)');

// --- Reempaque pendiente: dos cargas del mismo cliente consolidadas ---------
// Las dos cargas de reempaque se registraron con clientes distintos; se usa solo la primera completa.
$cargaReempaque = $porCarga['reempaque'][0];
$clienteReempaque = (string) $pdo->query("SELECT cliente_id FROM cargas WHERE id = " . $pdo->quote($cargaReempaque))->fetchColumn();
$c->get(IniciarReempaqueHandler::class)->handle(new IniciarReempaqueCommand(
    'consolidation', $clienteReempaque, $ids($listas[$cargaReempaque]), $trafico, 'Consolidar todo en 2 cajas grandes',
));
$paso('Reempaque pendiente (Consolidar) con ' . count($listas[$cargaReempaque]) . ' bultos, para hacerlo en la PDA');

echo "\nListo. Las cargas de prueba llevan en notas \"[DATOS DE PRUEBA {$corrida}]\".\n";
