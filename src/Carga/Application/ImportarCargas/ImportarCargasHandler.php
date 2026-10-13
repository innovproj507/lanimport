<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ImportarCargas;

use Courier\Carga\Application\RegistrarCarga\RegistrarCargaCommand;
use Courier\Carga\Application\RegistrarCarga\RegistrarCargaHandler;
use Courier\Carga\Domain\ActaRecepcion;
use Courier\Carga\Domain\ActaRecepcionRepositoryInterface;
use Courier\Proveedor\Domain\Proveedor;
use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Application\CommandHandlerInterface;
use Courier\Shared\Application\CommandInterface;
use Courier\Shared\Domain\ValueObject\Uuid;
use Throwable;

/**
 * Crea las cargas de una carga masiva ya validada. Cada carga se registra por
 * separado (con su propia transaccion en RegistrarCargaHandler): si una falla,
 * las demas siguen y el resultado lo informa.
 */
final class ImportarCargasHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RegistrarCargaHandler $registrarCarga,
        private readonly ProveedorRepositoryInterface $proveedores,
        private readonly ActaRecepcionRepositoryInterface $actas,
    ) {
    }

    /**
     * @return array{creadas: array<int, array<string, mixed>>, fallidas: array<int, array<string, mixed>>, proveedoresCreados: array<int, string>}
     */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof ImportarCargasCommand);

        $creadas = [];
        $fallidas = [];
        $proveedoresCreados = [];
        $proveedoresPorNombre = [];

        foreach ($command->cargas as $carga) {
            if ($carga['errores'] !== []) {
                $fallidas[] = ['referencia' => $carga['referencia'], 'error' => implode(' ', $carga['errores'])];
                continue;
            }

            try {
                $proveedorIdentificador = $carga['proveedorIdentificador'];
                $proveedorNombre = $carga['proveedorNombre'];

                if ($carga['proveedorNuevo']) {
                    $clave = mb_strtolower($proveedorNombre);

                    if (!isset($proveedoresPorNombre[$clave])) {
                        // Pudo haberse registrado a mano entre la vista previa y la confirmacion.
                        $proveedor = $this->proveedorPorNombre($proveedorNombre);

                        if ($proveedor === null) {
                            $proveedor = $this->crearProveedor($proveedorNombre);
                            $proveedoresCreados[] = $proveedor->identificador() . ' - ' . $proveedor->nombre();
                        }

                        $proveedoresPorNombre[$clave] = $proveedor;
                    }

                    $proveedorIdentificador = $proveedoresPorNombre[$clave]->identificador();
                    $proveedorNombre = $proveedoresPorNombre[$clave]->nombre();
                }

                $resultado = $this->registrarCarga->handle(new RegistrarCargaCommand(
                    $carga['clienteId'],
                    $proveedorNombre,
                    $proveedorIdentificador,
                    $carga['aduaneroIdentificador'],
                    $carga['aduaneroNombre'],
                    $carga['contenedor'],
                    null,
                    [],
                    $command->usuarioId,
                    $carga['fechaIngreso'],
                    $carga['lineas'],
                    $carga['notas'],
                    null,
                    null,
                    $command->notificarClientes,
                ));

                $acta = null;
                if ($command->generarActa) {
                    $acta = ActaRecepcion::generarPorCargaMasiva(
                        Uuid::generate(),
                        $resultado->cargaId,
                        $carga['clienteId'],
                        $carga['marcas'] !== '' ? $carga['marcas'] : null,
                        (int) round((float) $carga['bultos']),
                        $command->nombreArchivo,
                        $command->usuarioId,
                    );
                    $this->actas->save($acta);
                }

                $creadas[] = [
                    'referencia' => $carga['referencia'],
                    'cargaId' => $resultado->cargaId,
                    'tracking' => $resultado->trackingNumero,
                    'cliente' => $carga['clienteNombre'],
                    'bultos' => $carga['bultos'],
                    'acta' => $acta !== null,
                    'correo' => $resultado->correoEstado,
                ];
            } catch (Throwable $e) {
                $fallidas[] = ['referencia' => $carga['referencia'], 'error' => $e->getMessage()];
            }
        }

        return [
            'creadas' => $creadas,
            'fallidas' => $fallidas,
            'proveedoresCreados' => $proveedoresCreados,
        ];
    }

    private function proveedorPorNombre(string $nombre): ?Proveedor
    {
        foreach ($this->proveedores->findAll() as $proveedor) {
            if (mb_strtolower($proveedor->nombre()) === mb_strtolower($nombre)) {
                return $proveedor;
            }
        }

        return null;
    }

    /** Siguiente identificador libre con el formato de los existentes: PROV-0001, PROV-0002... */
    private function crearProveedor(string $nombre): Proveedor
    {
        $mayor = 0;

        foreach ($this->proveedores->findAll() as $proveedor) {
            if (preg_match('/^PROV-(\d+)$/i', $proveedor->identificador(), $m)) {
                $mayor = max($mayor, (int) $m[1]);
            }
        }

        do {
            $identificador = sprintf('PROV-%04d', ++$mayor);
        } while ($this->proveedores->existsIdentificador($identificador));

        $proveedor = Proveedor::registrar(Uuid::generate(), $identificador, mb_substr($nombre, 0, 190));
        $this->proveedores->save($proveedor);

        return $proveedor;
    }
}
