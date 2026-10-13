<?php

declare(strict_types=1);

namespace Courier\Carga\Application\ImportarCargas;

use Courier\Aduanero\Domain\AduaneroRepositoryInterface;
use Courier\Carga\Domain\ValueObject\Contenedor;
use Courier\Cliente\Domain\ClienteRepositoryInterface;
use Courier\Proveedor\Domain\ProveedorRepositoryInterface;
use Courier\Shared\Domain\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Valida las filas de una carga masiva y arma el plan de importacion (vista previa),
 * sin guardar nada. Las filas con la misma "referencia" forman una carga; cada fila
 * es una linea de mercancia. El plan es un arreglo simple para guardarlo en sesion
 * entre la vista previa y la confirmacion.
 */
final class PrepararImportacionCargas
{
    public const MAX_FILAS = 1000;

    /** Columnas de la plantilla: clave normalizada => [encabezado, obligatoria, descripcion]. */
    public const COLUMNAS = [
        'referencia' => ['Referencia', true, 'Agrupa las filas de una misma carga. Use el mismo valor en todas las lineas de esa carga (ej. su numero de guia o factura).'],
        'fecha_de_ingreso' => ['Fecha de ingreso', false, 'dd/mm/aaaa o dd/mm/aaaa hh:mm. Si se deja vacia se usa la fecha y hora de la carga masiva.'],
        'cliente' => ['Cliente', true, 'Codigo del cliente, su correo, o su nombre exacto (ver hoja Clientes).'],
        'proveedor' => ['Proveedor', true, 'Identificador (PROV-0001) o nombre del proveedor. Si no existe, se crea automaticamente.'],
        'aduanero' => ['Aduanero', false, 'Identificador del aduanero (ver hoja Aduaneros). Opcional.'],
        'contenedor' => ['Contenedor', false, 'Numero de contenedor: 4 letras y 7 numeros (ej. MSCU1234567). Opcional.'],
        'descripcion' => ['Descripcion', true, 'Descripcion de la mercancia de esta linea.'],
        'marca' => ['Marca', false, 'Marca que va en la etiqueta del bulto. Opcional.'],
        'unidad_de_medida' => ['Unidad de medida', false, 'Cajas, sacos, rollos, bultos... Si se deja vacia se usa "BULTOS".'],
        'cantidad' => ['Cantidad', true, 'Cantidad de bultos de esta linea (numero mayor que cero).'],
        'largo' => ['Largo (cm)', false, 'Opcional.'],
        'ancho' => ['Ancho (cm)', false, 'Opcional.'],
        'alto' => ['Alto (cm)', false, 'Opcional.'],
        'peso' => ['Peso (kg)', false, 'Opcional.'],
        'valor' => ['Valor', false, 'Valor declarado. Opcional.'],
        'notas' => ['Notas', false, 'Notas internas de la carga. Opcional.'],
    ];

    /** Otros nombres de columna aceptados => clave normalizada. */
    private const ALIAS = [
        'ref' => 'referencia',
        'fecha' => 'fecha_de_ingreso',
        'fecha_ingreso' => 'fecha_de_ingreso',
        'unidad' => 'unidad_de_medida',
        'unidad_medida' => 'unidad_de_medida',
        'uom' => 'unidad_de_medida',
        'bultos' => 'cantidad',
        'nota' => 'notas',
    ];

    public function __construct(
        private readonly ClienteRepositoryInterface $clientes,
        private readonly ProveedorRepositoryInterface $proveedores,
        private readonly AduaneroRepositoryInterface $aduaneros,
    ) {
    }

    /**
     * @param array<int, array<string, string>> $filas filas leidas del archivo (con '_fila')
     * @return array{cargas: array<int, array<string, mixed>>, totalFilas: int, proveedoresNuevos: array<int, string>}
     */
    public function preparar(array $filas): array
    {
        if ($filas === []) {
            throw new ValidationException('El archivo no tiene filas con datos (solo el encabezado).');
        }

        if (count($filas) > self::MAX_FILAS) {
            throw new ValidationException('El archivo tiene ' . count($filas) . ' filas; el maximo por carga masiva es ' . self::MAX_FILAS . '.');
        }

        $filas = array_map(fn (array $f) => $this->conAlias($f), $filas);
        $faltantes = array_keys(array_filter(
            self::COLUMNAS,
            static fn (array $c, string $clave) => $c[1] && !array_key_exists($clave, $filas[0]),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($faltantes !== []) {
            $nombres = array_map(static fn (string $k) => self::COLUMNAS[$k][0], $faltantes);
            throw new ValidationException('Faltan columnas obligatorias: ' . implode(', ', $nombres) . '. Use la plantilla del sistema.');
        }

        [$clientesPorClave, $clientesPorNombre] = $this->indiceClientes();
        $proveedores = $this->indiceProveedores();
        $aduaneros = $this->indiceAduaneros();

        // Agrupar por referencia conservando el orden del archivo.
        $grupos = [];
        foreach ($filas as $fila) {
            $referencia = trim($fila['referencia'] ?? '');
            $grupos[$referencia !== '' ? $referencia : '(sin referencia, fila ' . $fila['_fila'] . ')'][] = $fila;
        }

        $cargas = [];
        $proveedoresNuevos = [];

        foreach ($grupos as $referencia => $filasCarga) {
            $primera = $filasCarga[0];
            $errores = [];
            $numerosFila = array_map(static fn ($f) => (int) $f['_fila'], $filasCarga);

            if (trim($primera['referencia'] ?? '') === '') {
                $errores[] = 'Falta la referencia.';
            }

            // Una carga tiene un solo cliente/proveedor/etc.: las filas que lo repitan deben coincidir.
            // Se compara lo que se encontro (codigo y nombre del mismo proveedor son el mismo), no el texto.
            $identidad = [
                'cliente' => static function (string $v) use ($clientesPorClave, $clientesPorNombre): string {
                    $k = mb_strtolower($v);
                    return ($clientesPorClave[$k] ?? null)?->id() ?? (count($clientesPorNombre[$k] ?? []) === 1 ? $clientesPorNombre[$k][0]->id() : $k);
                },
                'proveedor' => static fn (string $v): string => ($proveedores[mb_strtolower($v)] ?? null)?->id() ?? mb_strtolower($v),
                'aduanero' => static fn (string $v): string => mb_strtolower($v),
                'contenedor' => static fn (string $v): string => strtoupper($v),
                'fecha_de_ingreso' => static fn (string $v): string => $v,
            ];

            foreach ($identidad as $campo => $resolver) {
                $porIdentidad = [];
                foreach ($filasCarga as $f) {
                    $v = trim($f[$campo] ?? '');
                    if ($v !== '') {
                        $porIdentidad[$resolver($v)] = $v;
                    }
                }
                if (count($porIdentidad) > 1) {
                    $errores[] = 'Las filas de esta referencia tienen distinto ' . mb_strtolower(self::COLUMNAS[$campo][0]) . ': ' . implode(' / ', $porIdentidad) . '.';
                }
            }

            // Cliente
            $textoCliente = trim($primera['cliente'] ?? '');
            $cliente = null;
            if ($textoCliente === '') {
                $errores[] = 'Falta el cliente.';
            } else {
                $clave = mb_strtolower($textoCliente);
                $cliente = $clientesPorClave[$clave] ?? null;
                if ($cliente === null && isset($clientesPorNombre[$clave])) {
                    $cliente = count($clientesPorNombre[$clave]) === 1 ? $clientesPorNombre[$clave][0] : null;
                    if ($cliente === null) {
                        $errores[] = "Hay varios clientes llamados \"{$textoCliente}\": use el codigo o el correo.";
                    }
                } elseif ($cliente === null) {
                    $errores[] = "Cliente \"{$textoCliente}\" no encontrado (o no esta aprobado).";
                }
            }

            // Proveedor (si no existe, se creara)
            $textoProveedor = trim($primera['proveedor'] ?? '');
            $proveedor = null;
            $proveedorNuevo = false;
            if ($textoProveedor === '') {
                $errores[] = 'Falta el proveedor.';
            } else {
                $proveedor = $proveedores[mb_strtolower($textoProveedor)] ?? null;
                if ($proveedor === null) {
                    $proveedorNuevo = true;
                    $proveedoresNuevos[mb_strtolower($textoProveedor)] = $textoProveedor;
                }
            }

            // Aduanero (opcional)
            $textoAduanero = trim($primera['aduanero'] ?? '');
            $aduanero = null;
            if ($textoAduanero !== '') {
                $aduanero = $aduaneros[mb_strtolower($textoAduanero)] ?? null;
                if ($aduanero === null) {
                    $errores[] = "Aduanero \"{$textoAduanero}\" no encontrado.";
                }
            }

            // Contenedor (opcional)
            $contenedor = strtoupper(trim($primera['contenedor'] ?? ''));
            if ($contenedor !== '') {
                try {
                    new Contenedor($contenedor);
                } catch (ValidationException) {
                    $errores[] = "Contenedor \"{$contenedor}\" invalido: deben ser 4 letras y 7 numeros.";
                }
            }

            // Fecha de ingreso (opcional)
            $fechaIngreso = (new DateTimeImmutable())->format('Y-m-d H:i');
            $textoFecha = trim($primera['fecha_de_ingreso'] ?? '');
            if ($textoFecha !== '') {
                $fecha = $this->fecha($textoFecha);
                if ($fecha === null) {
                    $errores[] = "Fecha de ingreso \"{$textoFecha}\" invalida: use dd/mm/aaaa.";
                } else {
                    $fechaIngreso = $fecha->format('Y-m-d H:i');
                }
            }

            // Lineas
            $lineas = [];
            $marcas = [];
            $notas = [];
            foreach ($filasCarga as $f) {
                $n = $f['_fila'];
                $descripcion = trim($f['descripcion'] ?? '');
                $cantidad = $this->numero($f['cantidad'] ?? '');

                if ($descripcion === '') {
                    $errores[] = "Fila {$n}: falta la descripcion.";
                }

                if ($cantidad === null || $cantidad <= 0) {
                    $errores[] = "Fila {$n}: la cantidad debe ser un numero mayor que cero.";
                }

                foreach (['largo', 'ancho', 'alto', 'peso', 'valor'] as $campo) {
                    if (trim($f[$campo] ?? '') !== '' && $this->numero($f[$campo]) === null) {
                        $errores[] = "Fila {$n}: " . mb_strtolower(self::COLUMNAS[$campo][0]) . " \"{$f[$campo]}\" no es un numero.";
                    }
                }

                $marca = trim($f['marca'] ?? '');
                if ($marca !== '') {
                    $marcas[$marca] = true;
                }

                if (trim($f['notas'] ?? '') !== '') {
                    $notas[trim($f['notas'])] = true;
                }

                $lineas[] = [
                    'descripcion' => $descripcion,
                    'marca' => $marca !== '' ? $marca : null,
                    'unidadMedida' => trim($f['unidad_de_medida'] ?? '') !== '' ? trim($f['unidad_de_medida']) : 'BULTOS',
                    'cantidad' => $cantidad ?? 0.0,
                    'largo' => $this->numero($f['largo'] ?? '') ?? 0.0,
                    'ancho' => $this->numero($f['ancho'] ?? '') ?? 0.0,
                    'alto' => $this->numero($f['alto'] ?? '') ?? 0.0,
                    'peso' => $this->numero($f['peso'] ?? '') ?? 0.0,
                    'valor' => $this->numero($f['valor'] ?? '') ?? 0.0,
                    'cubicajePies' => null,
                ];
            }

            $cargas[] = [
                'referencia' => $referencia,
                'filas' => $numerosFila,
                'clienteId' => $cliente?->id(),
                'clienteNombre' => $cliente?->nombreCompleto() ?? $textoCliente,
                'proveedorNombre' => $proveedor?->nombre() ?? $textoProveedor,
                'proveedorIdentificador' => $proveedor?->identificador(),
                'proveedorNuevo' => $proveedorNuevo,
                'aduaneroIdentificador' => $aduanero?->identificador(),
                'aduaneroNombre' => $aduanero?->nombre(),
                'contenedor' => $contenedor !== '' ? $contenedor : null,
                'fechaIngreso' => $fechaIngreso,
                'notas' => $notas !== [] ? implode("\n", array_keys($notas)) : null,
                'marcas' => implode(', ', array_keys($marcas)),
                'lineas' => $lineas,
                'bultos' => array_sum(array_column($lineas, 'cantidad')),
                'errores' => array_values(array_unique($errores)),
            ];
        }

        return [
            'cargas' => $cargas,
            'totalFilas' => count($filas),
            'proveedoresNuevos' => array_values($proveedoresNuevos),
        ];
    }

    /** @param array<string, string> $fila */
    private function conAlias(array $fila): array
    {
        foreach (self::ALIAS as $alias => $clave) {
            if (array_key_exists($alias, $fila) && !array_key_exists($clave, $fila)) {
                $fila[$clave] = $fila[$alias];
            }
        }

        return $fila;
    }

    /** @return array{0: array<string, \Courier\Cliente\Domain\Cliente>, 1: array<string, array<int, \Courier\Cliente\Domain\Cliente>>} */
    private function indiceClientes(): array
    {
        $porClave = [];
        $porNombre = [];

        foreach ($this->clientes->findApproved() as $cliente) {
            if ($cliente->codigo() !== null && $cliente->codigo() !== '') {
                $porClave[mb_strtolower($cliente->codigo())] = $cliente;
            }

            $porClave[mb_strtolower((string) $cliente->email())] = $cliente;
            $porNombre[mb_strtolower($cliente->nombreCompleto())][] = $cliente;

            if (mb_strtolower($cliente->nombre()) !== mb_strtolower($cliente->nombreCompleto())) {
                $porNombre[mb_strtolower($cliente->nombre())][] = $cliente;
            }
        }

        return [$porClave, $porNombre];
    }

    /** @return array<string, \Courier\Proveedor\Domain\Proveedor> */
    private function indiceProveedores(): array
    {
        $indice = [];

        foreach ($this->proveedores->findAll() as $proveedor) {
            $indice[mb_strtolower($proveedor->identificador())] = $proveedor;
            $indice[mb_strtolower($proveedor->nombre())] ??= $proveedor;
        }

        return $indice;
    }

    /** @return array<string, \Courier\Aduanero\Domain\Aduanero> */
    private function indiceAduaneros(): array
    {
        $indice = [];

        foreach ($this->aduaneros->findAllActivos() as $aduanero) {
            $indice[mb_strtolower($aduanero->identificador())] = $aduanero;
        }

        return $indice;
    }

    /** Acepta "1.234,5", "1,234.5", "12,5" y "12.5". */
    private function numero(string $texto): ?float
    {
        $texto = str_replace(' ', '', trim($texto));

        if ($texto === '') {
            return null;
        }

        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = strrpos($texto, ',') > strrpos($texto, '.')
                ? str_replace(['.', ','], ['', '.'], $texto)
                : str_replace(',', '', $texto);
        } else {
            $texto = str_replace(',', '.', $texto);
        }

        return is_numeric($texto) ? (float) $texto : null;
    }

    /** dd/mm/aaaa [hh:mm], aaaa-mm-dd [hh:mm] o numero de serie de Excel. */
    private function fecha(string $texto): ?DateTimeImmutable
    {
        if (is_numeric($texto) && (float) $texto > 20000 && (float) $texto < 80000) {
            $dias = (int) floor((float) $texto);
            $segundos = (int) round(((float) $texto - $dias) * 86400);

            return (new DateTimeImmutable('1899-12-30'))->modify("+{$dias} days +{$segundos} seconds");
        }

        foreach (['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d', 'd/m/y'] as $formato) {
            $fecha = DateTimeImmutable::createFromFormat('!' . $formato, $texto);

            if ($fecha !== false && DateTimeImmutable::getLastErrors() === false) {
                return $fecha;
            }
        }

        return null;
    }
}
