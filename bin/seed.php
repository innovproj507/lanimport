<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Courier\Auth\Domain\Rol;
use Courier\Auth\Domain\Usuario;
use Courier\Auth\Infrastructure\Persistence\PdoUsuarioRepository;
use Courier\Auth\Infrastructure\Security\PasswordHasher;
use Courier\Shared\Domain\ValueObject\EmailAddress;
use Courier\Shared\Domain\ValueObject\Uuid;
use Courier\Shared\Infrastructure\Config;
use Courier\Shared\Infrastructure\Persistence\ConnectionFactory;
use Ramsey\Uuid\Uuid as RamseyUuid;

$basePath = dirname(__DIR__);
Config::load($basePath);

$connection = ConnectionFactory::getInstance();
$usuarioRepository = new PdoUsuarioRepository($connection);
$hasher = new PasswordHasher();

const DEMO_PASSWORD = 'Demo1234!';

$demoUsuarios = [
    ['nombre' => 'Cliente Demo', 'email' => 'cliente@courier.local', 'rol' => Rol::CLIENTE],
    ['nombre' => 'Aduanero Demo', 'email' => 'aduanero@courier.local', 'rol' => Rol::ADUANERO],
    ['nombre' => 'Operaciones Demo', 'email' => 'operaciones@courier.local', 'rol' => Rol::OPERACIONES],
    ['nombre' => 'Bodega Demo', 'email' => 'bodega@courier.local', 'rol' => Rol::BODEGA],
    ['nombre' => 'Gerente Demo', 'email' => 'gerente@courier.local', 'rol' => Rol::GERENTE],
    ['nombre' => 'Admin Demo', 'email' => 'admin@courier.local', 'rol' => Rol::ADMIN],
];

$usuarioIds = [];

foreach ($demoUsuarios as $datos) {
    $usuario = Usuario::registrar(
        Uuid::generate(),
        $datos['nombre'],
        new EmailAddress($datos['email']),
        $hasher->hash(DEMO_PASSWORD),
        $datos['rol'],
    );

    $usuarioRepository->save($usuario);
    $usuarioIds[$datos['rol']->value] = $usuario->id();

    echo "Usuario creado: {$datos['email']} / " . DEMO_PASSWORD . " (rol: {$datos['rol']->value})\n";
}

$clienteId = RamseyUuid::uuid4()->toString();
$stmt = $connection->prepare(
    'INSERT INTO clientes (id, usuario_id, nombre, email, telefono, direccion, created_at)
     VALUES (:id, :usuario_id, :nombre, :email, :telefono, :direccion, NOW())'
);
$stmt->execute([
    ':id' => $clienteId,
    ':usuario_id' => $usuarioIds[Rol::CLIENTE->value],
    ':nombre' => 'Cliente Demo S.A.',
    ':email' => 'cliente@courier.local',
    ':telefono' => '+502 5555-0000',
    ':direccion' => 'Zona 10, Ciudad de Guatemala',
]);
echo "Cliente demo creado: {$clienteId}\n";

$aduaneroId = RamseyUuid::uuid4()->toString();
$stmt = $connection->prepare(
    'INSERT INTO aduaneros (id, identificador, nombre, usuario_id, activo, created_at)
     VALUES (:id, :identificador, :nombre, :usuario_id, 1, NOW())'
);
$stmt->execute([
    ':id' => $aduaneroId,
    ':identificador' => 'ADU-0001',
    ':nombre' => 'Aduanero Demo',
    ':usuario_id' => $usuarioIds[Rol::ADUANERO->value],
]);
echo "Aduanero demo creado: identificador ADU-0001\n";

$ubicaciones = [];
foreach (['A', 'B'] as $zona) {
    for ($pasillo = 1; $pasillo <= 2; $pasillo++) {
        for ($nivel = 1; $nivel <= 3; $nivel++) {
            if (count($ubicaciones) >= 10) {
                break;
            }
            $ubicaciones[] = [
                'codigo' => sprintf('%s-%02d-%02d', $zona, $pasillo, $nivel),
                'zona' => $zona,
                'pasillo' => (string) $pasillo,
                'nivel' => (string) $nivel,
            ];
        }
    }
}

$stmtUbicacion = $connection->prepare(
    'INSERT INTO ubicaciones (id, codigo, zona, pasillo, nivel, activo, created_at)
     VALUES (:id, :codigo, :zona, :pasillo, :nivel, 1, NOW())
     ON DUPLICATE KEY UPDATE zona = VALUES(zona)'
);

foreach ($ubicaciones as $ubicacion) {
    $stmtUbicacion->execute([
        ':id' => RamseyUuid::uuid4()->toString(),
        ':codigo' => $ubicacion['codigo'],
        ':zona' => $ubicacion['zona'],
        ':pasillo' => $ubicacion['pasillo'],
        ':nivel' => $ubicacion['nivel'],
    ]);
}
echo "Ubicaciones demo creadas: " . count($ubicaciones) . "\n";

echo "\nCliente ID para pruebas (cliente_id): {$clienteId}\n";
echo "Aduanero identificador para pruebas: ADU-0001\n";
echo "Seed completado.\n";
